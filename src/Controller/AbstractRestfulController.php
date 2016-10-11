<?php
namespace BS\Controller;


use BS\Authentication\UnAuthenticatedException;
use BS\Db\Model\AbstractModel;
use BS\Db\TableGateway\AbstractTableGateway;
use BS\Exception;
use BS\I18n\Translator\TranslatorAwareInterface;
use BS\I18n\Translator\TranslatorAwareTrait;
use BS\Traits\LoggerAwareTrait;
use BS\Utility\Measure;
use Psr\Log\LoggerAwareInterface;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Db\Sql\Select;
use Zend\EventManager\Event;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Json\Json;
use Zend\Mvc\Controller\AbstractRestfulController as Base;
use Zend\Mvc\Exception\DomainException;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceManager;
use Zend\View\Model\JsonModel;
use BS\Exception\AbstractWithParamException;
use Zend\Json\Decoder;
use BS\Exception\CommonException;

/**
 * @property JsonModel      $viewModel
 * @property Response       $response
 * @property ServiceManager $serviceLocator
 *
 * @method Request getRequest()
 * @method Response getResponse()
 */
abstract class AbstractRestfulController extends Base implements LoggerAwareInterface, TranslatorAwareInterface
{
    use LoggerAwareTrait, TranslatorAwareTrait;

    const DEFAULT_RECORD_LIMIT = 50;
    const MAXIMUM_RECORD_LIMIT = 500;
    const DEFAULT_RECORD_START = 0;

    const EVENT_CREATE_PRE = 'create.pre';
    const EVENT_CREATE_POST = 'create.post';
    const EVENT_READ_PRE = 'read.pre';
    const EVENT_READ_POST = 'read.post';
    const EVENT_UPDATE_PRE = 'update.pre';
    const EVENT_UPDATE_POST = 'update.post';
    const EVENT_DELETE_PRE = 'delete.pre';
    const EVENT_DELETE_POST = 'delete.post';
    const EVENT_GET_PRE = 'get.pre';
    const EVENT_GET_POST = 'get.post';
    const EVENT_INDEX_PRE = 'index.pre';
    const EVENT_INDEX_POST = 'index.post';

    const EVENT_AUTH_BEFORE = 'auth.before';
    const EVENT_AUTH_AFTER = 'auth.after';

    protected $selectLimit;
    protected $selectOffset;

    static $jsonContentParams = [];

    /**
     * @var string Model ClassName for this Controller
     */
    protected $modelClass;
    /**
     * @var string TableGateway ClassName for this Controller
     */
    protected $tableGatewayClass;
    /**
     * @var \BS\Db\TableGateway\AbstractTableGateway
     */
    protected $tableGatewayInstance;

    public function __construct()
    {
        $this->getEventManager()->attach(self::EVENT_CREATE_PRE, [$this, 'createPre']);
        $this->getEventManager()->attach(self::EVENT_CREATE_POST, [$this, 'createPost']);
        $this->getEventManager()->attach(self::EVENT_READ_PRE, [$this, 'readPre']);
        $this->getEventManager()->attach(self::EVENT_READ_POST, [$this, 'readPost']);
        $this->getEventManager()->attach(self::EVENT_UPDATE_PRE, [$this, 'updatePre']);
        $this->getEventManager()->attach(self::EVENT_UPDATE_POST, [$this, 'updatePost']);
        $this->getEventManager()->attach(self::EVENT_DELETE_PRE, [$this, 'deletePre']);
        $this->getEventManager()->attach(self::EVENT_DELETE_POST, [$this, 'deletePost']);
        $this->getEventManager()->attach(self::EVENT_GET_PRE, [$this, 'getPre']);
        $this->getEventManager()->attach(self::EVENT_GET_POST, [$this, 'getPost']);
        $this->getEventManager()->attach(self::EVENT_INDEX_PRE, [$this, 'indexPre']);
        $this->getEventManager()->attach(self::EVENT_INDEX_POST, [$this, 'indexPost']);
        $this->getEventManager()->attach(self::EVENT_AUTH_BEFORE, [$this, 'beforeAuth']);
        $this->getEventManager()->attach(self::EVENT_AUTH_AFTER, [$this, 'afterAuth']);
    }

    public function onDispatch(MvcEvent $event)
    {
        try {
            $this->viewModel = new JsonModel();
            $this->viewModel->setTerminal(true);

            Json::$useBuiltinEncoderDecoder = false;

            $this->getEventManager()->trigger(self::EVENT_AUTH_BEFORE, $this, []);
            $this->initAuthenticationService($event);
            $this->getEventManager()->trigger(self::EVENT_AUTH_AFTER, $this, []);

            // Retry /index/id route if current route of /id is invalid
            $routeMatch = $event->getRouteMatch();
            if (!$routeMatch) {
                throw new DomainException('Missing route matches; unsure how to retrieve action');
            }
            /** @var \Zend\Http\PhpEnvironment\Request $request */
            $request = $event->getRequest();
            $action  = $routeMatch->getParam('action', false);

            if ($action) {
                // Handle arbitrary methods, ending in Action
                $method = static::getMethodFromAction($action);
                if (!method_exists($this, $method)) {
                    $routeMatch->setParam('id', $action);
                    $routeMatch->setParam('action', false);
                    if (strtolower($request->getMethod()) == 'get') {
                        $routeMatch->setParam('action', 'index');
                    }
                }
            }
            if (extension_loaded('newrelic')) {
                newrelic_name_transaction($request->getUri()->getPath());
            }


            return parent::onDispatch($event);
        } catch (\Exception $exception) {
            // Rollback DB Transactions
            $Connection = $this->getDbConnection();
            if ($Connection->isConnected() && $Connection->inTransaction()) {
                $Connection->rollback();
            }

            switch (true) {
                case $exception instanceof UnAuthenticatedException:
                    $message = $this->t('Please login first');
                    break;
                case $exception instanceof AbstractWithParamException:
                    $message = vsprintf($this->t($exception->getMessage()), $exception->getMessageParams());
                    break;
                default:
                    $message = $this->t($exception->getMessage());
                    break;
            }

            $this->respond(false, [], $message, $exception->getCode());

            $event->setController($this);
            $event->setResult($this->viewModel);
            $event->setError($message);
            $event->setParam('exception', $exception);
        }

        return $this->viewModel;
    }

    public function create($data)
    {
        return $this->postAction($data);
    }

    public function update($id, $data)
    {

        return $this->postAction($data);
    }

    public function replaceList($data)
    {
        throw new \BadMethodCallException('RESTful method replacelist not supported yet');
    }

    public function delete($id)
    {
        return $this->deleteAction($id);
    }

    public function get($id)
    {
        $this->getEventManager()->trigger(self::EVENT_GET_PRE, $this, $this->params()->fromQuery());
        $this->indexAction($id);
        $this->getEventManager()->trigger(self::EVENT_GET_POST, $this, $this->params()->fromQuery());

        return $this->viewModel;
    }

    public function getList()
    {
        $this->getEventManager()->trigger(self::EVENT_INDEX_PRE, $this, $this->params()->fromQuery());
        $this->indexAction();
        $this->getEventManager()->trigger(self::EVENT_INDEX_POST, $this, $this->params()->fromQuery());

        return $this->viewModel;
    }

    public function indexAction($id = false)
    {
        $data = $this->getEventManager()->trigger(self::EVENT_READ_PRE, $this, $this->params()->fromQuery())->last();

        $tableGateway = $this->getTableGateway();
        $select       = $this->prepareSelect();

        if (false == $id) {
            $id = $this->getParam('id');
        }

        if ($id) {
            $resultSet = $this->getTableGateway()->selectWith(
                $tableGateway->injectSelect($select->where(
                    $this->getTableGateway()->decodeCompositeKey($id)
                )->limit(1), $data)->getInjectedSelect()
            );
            $foundRows = $resultSet->count();
            if ($foundRows != 1) {
                throw new CommonException('can\'t find the record');
            }
        } else {
            list($resultSet, $foundRows) = $tableGateway->injectSelect($select, $data)->fetchAll();
        }

        $resultSet = $resultSet->toArray();
        $resultSet = $this->getMeasureService()->getConvertList($resultSet);

        if ($id) {
            $redata = current($resultSet);
        } else {
            $redata = [
                'total' => $foundRows,
                'start' => $this->selectOffset,
                'limit' => $this->selectLimit,
                'list'  => $resultSet
            ];
        }

        $this->respond(true, $redata);

        $this->getEventManager()->trigger(self::EVENT_READ_POST, $this);

        return $this->viewModel;
    }


    public function postAction($data)
    {
        /**
         * @var \BS\Db\Model\AbstractModel $Model
         */
        $Model         = new $this->modelClass($data);
        $isUpdate      = false;
        $compositeKeys = $this->getTableGateway()->decodeCompositeKey($Model->getId());
        /** @var AbstractModel $OldModel */
        if (count($compositeKeys) == count($this->getTableGateway()->getPrimaryKeys())) {
            if ($OldModel = $this->getTableGateway()->select($compositeKeys)->current()) {
                $isUpdate = true;
            }
        }

        if ($isUpdate) {
            $data  = $this->getEventManager()->trigger(self::EVENT_UPDATE_PRE, $this, $data)->last();
            $data  = $this->getMeasureService()->saveConvertArray($data);
            $Model = $this->getTableGateway()->getModel($OldModel->getArrayCopy())->exchangeArray($data);
            $this->getTableGateway()->saveUpdate($Model, $OldModel);
            $Model = $this->getTableGateway()->get($Model->getId());
            $this->getEventManager()->trigger(self::EVENT_UPDATE_POST, $this, ['data' => $data, 'Model' => $Model]);
        } else {
            $data = $this->getEventManager()->trigger(self::EVENT_CREATE_PRE, $this, $data)->last();
            $data = $this->getMeasureService()->saveConvertArray($data);
            $Model->exchangeArray($data);
            $id    = $this->getTableGateway()->saveInsert($Model);
            $Model = $this->getTableGateway()->get($id);
            $this->getEventManager()->trigger(self::EVENT_CREATE_POST, $this, ['data' => $data, 'Model' => $Model]);
        }

        return $this->respond(true, $Model->getArrayCopy());
    }

    public function deleteAction($id = false)
    {
        if (false == $id) {
            $id = $this->getParam('id');
        }

        $this->getEventManager()->trigger(self::EVENT_DELETE_PRE, $this, ['id' => $id]);
        $tableGateway = $this->getTableGateway();
        $tableGateway->delete($this->getTableGateway()->decodeCompositeKey($id));
        $this->getEventManager()->trigger(self::EVENT_DELETE_POST, $this);

        return $this->respond();
    }

    public function deleteListAction()
    {
        return $this->deleteList($this->getParams());
    }

    public function prepareSelect(Select $select = null)
    {
        if (empty($select)) {
            $select = $this->getTableGateway()->getSql()->select();
        }

        $select = $this->prepareSelectSetSelectFields($select);
        $select = $this->prepareSelectSetFilters($select);
        $select = $this->prepareSelectSetSearch($select);
        $select = $this->prepareSelectSetLimit($select);
        $select = $this->prepareSelectSetWhere($select);
        $select = $this->prepareSelectSetSortInfo($select);
        $select = $this->prepareSelectSetDateLimit($select);

        return $select;
    }


    protected function prepareSelectSetSelectFields(Select $select)
    {
        $params       = $this->getParams();
        $selectFields = [];
        if (isset($params['selectFields'])) {
            $selectFields = explode(',', $params['selectFields']);
        }

        if (count($selectFields) == 0) {
            $selectFields[] = Select::SQL_STAR;
        } else {
            //select all primary keys whatsoever
            $primaryKeys  = $this->getTableGateway()->getPrimaryKeys();
            $selectFields = array_merge($selectFields, $primaryKeys);
        }

        $cols = [];
        foreach ($selectFields as $column) {
            if (is_string($column) && $column != Select::SQL_STAR) {
                $columnString = str_replace('-', '.', $column);
                if (!strpos($columnString, '.')) {
                    $columnString = $this->getTableGateway()->getTable() . '.' . $columnString;
                }
                $cols[$column] = new Expression($columnString);
            } else {
                $cols[] = $column;
            }
        }

        if ($cols && (count($cols) > 0)) {
            $select->columns($cols);
        }

        return $select;
    }

    /**
     * @param             $filter
     * @param Select|null $select
     *
     * @return mixed
     */
    protected function onBeforeProcessFilter($filter, Select $select = null)
    {
        return $filter;
    }

    /**
     * @param Select $select
     * @param array  $params
     *
     * @return Select
     * @codeCoverageIgnore Ignore code coverage here to prevent incorrect coverage detection
     */
    protected function prepareSelectSetFilters(Select $select, $params = [])
    {
        if (empty($params)) {
            $params = $this->getParams();
        }

        if (!empty($params['includeNullFields'])) {
            $nullFields = explode(',', $params['includeNullFields']);
            if (!empty($nullFields)) {
                $nullFields = array_map(function ($val) {
                    if (stripos($val, '-') > 0) {
                        return str_replace('-', '.', $val);
                    } else {
                        return $this->getTableGateway()->getTable() . '.' . $val;
                    }
                }, $nullFields);
            }
        }

        if (!empty($params['filter'])) {
            $filters          = Json::decode($params['filter'], Json::TYPE_ARRAY);
            $customizedFields = $this->getTableGateway()->getCustomizedFilterFields();
            foreach ($filters as $filter) {
                $conditionOperator = 'where';
                // Stupid ExtJS use 'property' as property name in Store Filter
                // And 'field' as property name in Grid Filter, I have to standarlise them
                $filter['field'] = empty($filter['property']) ? $filter['field'] : $filter['property'];
                if (isset($filter['field']) && array_key_exists($filter['field'], $customizedFields)) {
                    if (is_array($customizedFields[$filter['field']])) {
                        if (isset($customizedFields[$filter['field']]['use_having']) &&
                            $customizedFields[$filter['field']]['use_having'] == 1
                        ) {
                            $conditionOperator = 'having';
                            $filter['field']   = $customizedFields[$filter['field']]['field'];
                        }
                    } else {
                        $filter['field'] = $customizedFields[$filter['field']];
                    }
                } else {

                    $filter = $this->onBeforeProcessFilter($filter, $select);

                    if (empty($filter['field']) || !isset($filter['value'])) {
                        continue;
                    }

                    $filter['field'] = str_replace('-', '.', $filter['field']);

                    if (!@strlen($filter['field'])) {
                        continue;
                    }

                    if (is_string($filter['value'])) {
                        $filter['value'] = trim($filter['value']);
                        if (0 == strlen($filter['value'])) {
                            continue;
                        }
                    }

                    if (!strstr($filter['field'], '.')) {
                        $filter['field'] = $this->getTableGateway()->getTable() . '.' . $filter['field'];
                    }
                }
                switch (@$filter['type']) {
                    case 'string':
                        $select->$conditionOperator([
                            $filter['field'] . ' LIKE ?'
                            => '%' . $filter['value'] . '%',
                        ]);
                        break;
                    case 'boolean':
                        $select->$conditionOperator([
                            $filter['field'] . ' = ?'
                            => $filter['value'] == true ? 1 : 0,
                        ]);
                        break;
                    case 'numeric':
                        $operatorMap = [
                            'ne' => '!=',
                            'eq' => '=',
                            'lt' => '<=',
                            'gt' => '>=',
                        ];

                        $filter['comparison'] =
                            array_key_exists('comparison', $filter) ? $filter['comparison'] : 'eq';
                        if (!array_key_exists($filter['comparison'], $operatorMap)) {
                            continue;
                        }
                        if (isset($nullFields) && intval($filter['value']) <= 0 &&
                            in_array($filter['comparison'], ['eq', 'lt']) && in_array($filter['field'], $nullFields)
                        ) {
                            $select->$conditionOperator([$filter['field'] . ' IS NULL']);
                        } else {
                            $select->$conditionOperator([
                                $filter['field'] . ' ' . $operatorMap[$filter['comparison']] . ' ?'
                                => $filter['value'],
                            ]);
                        }
                        break;
                    case 'date':
                        $operatorMap = [
                            'ne' => '!=',
                            'eq' => '=',
                            'lt' => '<=',
                            'gt' => '>=',
                        ];

                        $filter['comparison'] =
                            array_key_exists('comparison', $filter) ? $filter['comparison'] : 'eq';
                        if (!array_key_exists($filter['comparison'], $operatorMap)) {
                            continue;
                        }
                        if ($filter['comparison'] == 'eq') {
                            $select->$conditionOperator([
                                $filter['field'] . " BETWEEN '? 00:00:00' AND '? 23:59:59'"
                                => [new Expression($filter['value']), new Expression($filter['value'])],
                            ]);
                        } else {
                            $select->$conditionOperator([
                                $filter['field'] . ' ' . $operatorMap[$filter['comparison']] . ' ?'
                                => new Expression("'" . $filter['value'] . "'"),
                            ]);
                        }
                        break;
                    case 'list':
                        $select->$conditionOperator->in(new Expression($filter['field']), $filter['value']);
                        break;
                    default:
                        $select->$conditionOperator([$filter['field'] . ' = ?' => $filter['value']]);
                }
            }
        }

        return $select;
    }


    /**
     * @param Select $select
     *
     * @return Select
     * @throws Exception
     */
    protected function prepareSelectSetSearch(Select $select)
    {
        $params = $this->getParams();
        if (array_key_exists('field', $params) && array_key_exists('query', $params)) {
            $select->where([
                "{$this->getTableGateway()->getTable()}.{$params['field']} LIKE ?" => "%{$params['query']}%",
            ]);
        } elseif (array_key_exists('fields', $params) && array_key_exists('operators', $params) &&
                  array_key_exists('values', $params)
        ) {
            $fields    = @$params['fields'];
            $operators = @$params['operators'];
            $values    = @$params['values'];

            if (sizeof($fields) != sizeof($operators)) {
                throw new CommonException('Search criterias wrong!');
            }
            if (($fields != null) && ($operators != null) && ($values != null)) {
                $this->_setConditions($select, $fields, $operators, $values);
            }
        }

        return $select;
    }

    private function _setConditions(Select $select, $fields, $operators, $values)
    {
        if (!is_array($values)) {
            return $select->where(["{$this->getTableGateway()->getTable()}.{$fields[0]} {$operators[0]} ?" => $values]);
        }

        foreach ($fields as $key => $value) {
            if (@$values[$key]) {
                if (strtoupper($operators[$key]) == 'LIKE') {
                    $values[$key] = "%$values[$key]%";
                }

                $select->where([
                    "{$this->getTableGateway()->getTable()}.{$fields[$key]} {$operators[$key]} ?" => $values[$key],
                ]);
            }
        }

        return $select;
    }

    /**
     * @param Select $select
     *
     * @return Select
     * @throws Exception
     */
    protected function prepareSelectSetWhere(Select $select)
    {
        $params      = $this->getParams();
        $primaryKeys = $this->getTableGateway()->getPrimaryKeys();
        $allKeys     = array_merge($primaryKeys, $this->getTableGateway()->getSearchFields());
        foreach ($allKeys as $key) {
            if (array_key_exists($key, $params)) {
                $arrField = explode('-', $key);
                if (count($arrField) < 2) {
                    array_unshift($arrField, $this->getTableGateway()->getTable());
                }
                $field = implode('.', $arrField);
                $select->where(["{$field} = ?" => $params[$key]]);
            }
        }

        return $select;
    }

    /**
     * @param Select $select
     *
     * @return Select
     */
    protected function prepareSelectSetSortInfo(Select $select)
    {
        $sortInfos            =
            $this->getParam('sort') ? Decoder::decode($this->getParam('sort'), Json::TYPE_ARRAY) : null;
        $customizedSortFields = $this->getTableGateway()->getCustomizedSortFields();
        if ($sortInfos) {
            foreach ($sortInfos as $sort) {
                if (in_array($sort['property'], $customizedSortFields)) {
                    $select->order($sort['property'] . ' ' . $sort['direction']);
                } else {
                    if (array_key_exists($sort['property'], $customizedSortFields)) {
                        $select->order($customizedSortFields[$sort['property']] . ' ' . $sort['direction']);
                    } else {
                        $sort['property'] = str_replace('-', '.', $sort['property']);
                        $sort['property'] = strpos($sort['property'], '.') ? $sort['property'] :
                            $this->getTableGateway()->getTable() . '.' . $sort['property'];
                        $select->order($sort['property'] . ' ' . $sort['direction']);
                    }
                }
            }
        }

        return $select;
    }

    protected function prepareSelectSetDateLimit(Select $select)
    {
        //TODO:read datelimit from company config
        /*$CompanyConfig = $this->serviceLocator->get(CompanyConfig::class);
        $config        = $CompanyConfig->getModuleConfiguration('site');
        if (isset($config->site->features->dateLimit)) {
            $select->where($this->getTableGateway()->getTable() . '.dateFrom',
                new Expression("DATE_SUB(NOW(),INTERVAL {$config->site->features->dateLimit} DAY)"));
        }

        return $select;*/

        return $select;
    }

    protected function prepareSelectSetLimit(Select $select)
    {
        $limit = $this->getParam('limit') && $this->getParam('limit') <= static::MAXIMUM_RECORD_LIMIT
            ? $this->getParam('limit')
            : static::DEFAULT_RECORD_LIMIT;

        $this->selectOffset = is_numeric($this->getParam('start')) && $this->getParam('start') >= 0 ?
            (int)$this->getParam('start') : static::DEFAULT_RECORD_START;
        $this->selectLimit  = static::DEFAULT_RECORD_LIMIT;

        if ($limit > 0) {
            $this->selectLimit = (int)$limit;
        } elseif ($limit == -1) {
            $this->selectLimit = -1;
        }

        if ($this->selectLimit != -1) {
            $select->limit($this->selectLimit);
        }

        return $select->offset($this->selectOffset);
    }


    protected function respond($success = true, $data = [], $message = '', $errCode = '', $refresh = false)
    {
        $this->viewModel->setVariables([
            'success' => $success,
            'errCode' => $errCode,
            'message' => $message,
            'data'    => $data,
        ]);

        if ($refresh !== false) {
            $this->viewModel->setVariable('refresh', $refresh);
        }

        return $this->viewModel;
    }

    /**
     * @param      $param
     * @param null $default
     *
     * @return array|null
     */
    public function getParam($param, $default = null)
    {
        if ($this->params()->fromPost($param) !== null) {
            return $this->params()->fromPost($param);
        }

        if ($this->params()->fromQuery($param) !== null) {
            return $this->params()->fromQuery($param);
        }

        if ($this->params()->fromRoute($param) !== null) {
            return $this->params()->fromRoute($param);
        }

        return is_null($this->getJsonContentParam($param)) ? $default : $this->getJsonContentParam($param);
    }

    /**
     * @return array
     */
    public function getParams()
    {
        $params = !empty($this->params()->fromPost()) ? $this->params()->fromPost() :
            (!empty($this->params()->fromQuery()) ? $this->params()->fromQuery() :
                $this->params()->fromRoute());

        return array_merge($params, $this->getJsonContentParam());
    }


    protected function getJsonContentParam($param = null)
    {
        if ($this->requestHasContentType($this->getRequest(), self::CONTENT_TYPE_JSON)) {
            if (empty(static::$jsonContentParams)) {
                if (!empty($this->getRequest()->getContent())) {
                    static::$jsonContentParams = Json::decode($this->getRequest()->getContent(), $this->jsonDecodeType);
                }
            }
        }

        return is_null($param) ? static::$jsonContentParams :
            (isset(static::$jsonContentParams[$param]) ? static::$jsonContentParams[$param] : null);

    }

    /**
     * @return AbstractTableGateway
     * @throws Exception
     */
    public function getTableGateway()
    {
        if (!$this->tableGatewayInstance) {
            if ($this->tableGatewayClass) {
                $this->tableGatewayInstance = $this->serviceLocator->get($this->tableGatewayClass);
            } else {
                throw new Exception('No TableGateway class has been defined in this Controller');
            }
        }

        return $this->tableGatewayInstance;
    }

    /**
     * @param AbstractTableGateway $tableGateway
     *
     * @return $this
     */
    public function setTableGateway(AbstractTableGateway $tableGateway)
    {
        $this->tableGatewayInstance = $tableGateway;

        return $this;
    }

    /**
     * @param Request $request
     *
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @param $func
     *
     * @return \Zend\View\Model\ViewModel
     * @throws Exception
     */
    protected function _callTableFunc($func)
    {
        $params = $this->getParams();
        $data   = call_user_func([$this->getTableGateway(), $func], $params);

        return $this->viewModel->setVariables(['data' => $data, 'success' => true]);
    }

    /**
     *
     * @throws Exception
     * @return Measure
     */
    protected function getMeasureService()
    {
        $Measure       = $this->serviceLocator->get(Measure::class)->init();
        $measureFields = $this->getTableGateway()->getMeasureField();

        if (isset($measureFields['lengthFields']) && !empty($measureFields['lengthFields'])) {
            $Measure->lengthColumnNames = $measureFields['lengthFields'];
        }
        if (isset($measureFields['weightFields']) && !empty($measureFields['weightFields'])) {
            $Measure->weightColumnNames = $measureFields['weightFields'];
        }
        if (isset($measureFields['volumeFields']) && !empty($measureFields['volumeFields'])) {
            $Measure->volumeColumnNames = $measureFields['volumeFields'];
        }

        return $Measure;
    }

    /**
     * @return \Zend\Db\Adapter\Driver\AbstractConnection
     */
    protected function getDbConnection()
    {
        /** @var \Zend\Db\Adapter\Driver\AbstractConnection $connection */
        $connection = $this->getDbAdapter()->getDriver()->getConnection();

        return $connection;
    }

    /**
     * @return \Zend\Db\Adapter\Adapter
     */
    protected function getDbAdapter()
    {
        return $this->serviceLocator->get('db');
    }

    public function createPre(Event $event)
    {
        return $event->getParams();
    }

    public function createPost(Event $event)
    {
    }

    public function readPre(Event $event)
    {
        return $event->getParams();
    }

    public function readPost(Event $event)
    {
    }

    public function updatePre(Event $event)
    {
        return $event->getParams();
    }

    public function updatePost(Event $event)
    {
    }

    public function deletePre(Event $event)
    {
        return $event->getParams();
    }

    public function deletePost(Event $event)
    {
    }

    public function getPre(Event $event)
    {
        return $event->getParams();
    }

    public function getPost(Event $event)
    {
    }

    public function indexPre(Event $event)
    {
        return $event->getParams();
    }

    public function indexPost(Event $event)
    {
    }

    public function deleteList($data)
    {
        if (!is_array($data)) {
            $data = $this->getParams();
        }
        $ids = $data['ids'];
        if (!is_array($ids)) {
            $ids = array_filter(explode('_', $ids));
        }
        $isCompositeKey = count($this->getTableGateway()->getPrimaryKeys()) > 1 ? true : false;


        $this->getEventManager()->trigger(self::EVENT_DELETE_PRE, $this, ['ids' => $ids]);
        if ($isCompositeKey) {
            foreach ($ids as $id) {
                $this->getTableGateway()->delete(
                    $this->getTableGateway()->decodeCompositeKey(str_replace('|', '_', $id)));
            }
        } else {
            $this->getTableGateway()->delete(['id' => $ids]);
        }
        $this->getEventManager()->trigger(self::EVENT_DELETE_POST, $this, ['ids' => $ids]);

        return $this->indexAction();
    }

    public function beforeAuth(Event $event)
    {
    }

    public function afterAuth(Event $event)
    {
    }

    public function getModuleName()
    {
        $controller = $this->params('controller');

        return substr($controller, 0, strpos($controller, '\\'));
    }

    public function getControllerName()
    {
        $controller = $this->params('controller');

        return substr($controller, strrpos($controller, '\\') + 1);
    }

    public function getActionName()
    {
        $action = $this->params('action');
        if (!$action) {
            $action = strtolower($this->getRequest()->getMethod());
        }

        return $action;
    }

    abstract function initAuthenticationService(MvcEvent $event);
}
