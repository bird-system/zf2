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
            $action = $routeMatch->getParam('action', false);
            if ($action) {
                // Handle arbitrary methods, ending in Action
                $method = static::getMethodFromAction($action);
                if (!method_exists($this, $method)) {
                    $routeMatch->setParam('id', $action);
                    $routeMatch->setParam('action', false);
                    /** @var Request $request */
                    $request = $event->getRequest();
                    if (strtolower($request->getMethod()) == 'get') {
                        $routeMatch->setParam('action', 'index');
                    }
                }
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
                default:
                    $message = $exception->getMessage();
                    break;
            }
            $this->setErrorInfo($event, $exception, $message);
        }

        return $this->viewModel;
    }

    public function setErrorInfo(MvcEvent $event, $exception, $message)
    {
        $this->viewModel->setVariables([
            'success'   => false,
            //TODO: through the exception get error code
            'errorCode' => get_class($exception),
            'message'   => $message
        ]);
        $event->setController($this);
        $event->setResult($this->viewModel);
        $event->setError($message);
        $event->setParam('exception', $exception);
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

        /* @var \Zend\Db\ResultSet\ResultSet $resultSet */
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
                throw new \Exception('can\'t found the record');
            }
        } else {
            list($resultSet, $foundRows) = $tableGateway->injectSelect($select, $data)->fetchAll();
        }

        $resultSet = $resultSet->toArray();
        $resultSet = $this->getMeasureService()->getConvertList($resultSet);

        $this->viewModel->setVariables([
            'success'   => true,
            'errorCode' => '',
            'message'   => '',
        ]);
        if ($id) {
            $this->viewModel->setVariable('data', current($resultSet));
        } else {
            $this->viewModel->setVariable('data', [
                'total' => $foundRows,
                'start' => $this->selectOffset,
                'limit' => $this->selectLimit,
                'list'  => $resultSet
            ]);
        }
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

        return $this->viewModel->setVariables([
            'success'   => true,
            'errorCode' => '',
            'message'   => '',
            'data'      => $Model->getArrayCopy(),
        ]);
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

        return $this->viewModel->setVariables([
            'success'   => true,
            'errorCode' => '',
            'message'   => '',
            'data'      => []
        ]);
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

        $limit = $this->getParam('limit') && $this->getParam('limit') <= self::MAXIMUM_RECORD_LIMIT
            ? $this->getParam('limit')
            : self::DEFAULT_RECORD_LIMIT;

        if ($limit != -1) {
            $offset             = $this->getParam('start') ? $this->getParam('start') : self::DEFAULT_RECORD_START;
            $this->selectLimit  = (int)$limit;
            $this->selectOffset = (int)$offset;
        } else {
            $this->selectLimit  = self::DEFAULT_RECORD_LIMIT;
            $this->selectOffset = self::DEFAULT_RECORD_START;
        }

        return $select->limit($this->selectLimit)->offset($this->selectOffset);

        return $select;
    }

    protected function respond($success = true, $message = null, $header = 200, $refresh = false)
    {
        if (isset($header)) {
            $this->response->setMetadata($header);
        }
        if (isset($success)) {
            $this->viewModel->setVariable('success', $success);
        }
        if (isset($message)) {
            $this->viewModel->setVariable('message', $message);
        }
        if (isset($refresh)) {
            $this->viewModel->setVariable('refresh', $refresh);
        }

        return $this->viewModel;
    }

    /**
     * @param      $param
     * @param null $default
     *
     * @return mixed|null
     */
    protected function getParam($param, $default = null)
    {
        if ($this->params()->fromPost($param) !== null) {
            return $this->params()->fromPost($param);
        }

        if ($this->params()->fromQuery($param) !== null) {
            return $this->params()->fromQuery($param);
        }

        return is_null($this->params()->fromRoute($param)) ? $default : $this->params()->fromRoute($param);
    }

    /**
     * @return mixed
     */
    protected function getParams()
    {
        return !empty($this->params()->fromPost()) ? $this->params()->fromPost() :
            (!empty($this->params()->fromQuery()) ? $this->params()->fromQuery() :
                $this->params()->fromRoute());
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
        return $this->getDbAdapter()->getDriver()->getConnection();
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
