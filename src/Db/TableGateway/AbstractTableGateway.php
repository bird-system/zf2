<?php
namespace BS\Db\TableGateway;

use BS\Controller\Exception\AppException;
use BS\Db\Model\AbstractModel;
use BS\Db\TableGateway\Exception\IncompleteCompositeKeyException;
use BS\Db\TableGateway\Exception\SeekException;
use BS\Exception;
use BS\I18n\Translator\TranslatorAwareInterface;
use BS\I18n\Translator\TranslatorAwareTrait;
use BS\Traits\LoggerAwareTrait;
use BS\Traits\ServiceLocatorTrait;
use BS\Utility\Utility;
use Camel\CaseTransformer;
use Camel\Format;
use Psr\Log\LoggerAwareInterface;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\I18n\Translator\Translator;

/**
 * Class AbstractTableGateway
 *
 * @package BS\Db\TableGateway
 * @method ResultSet selectWith(Select $select)
 * @method Adapter getAdapter()
 */
abstract class AbstractTableGateway extends TableGateway implements
    LoggerAwareInterface,
    TranslatorAwareInterface
{
    use ServiceLocatorTrait, LoggerAwareTrait, TranslatorAwareTrait;

    const CACHE_DAILY = 'CACHE_DAILY';
    const CACHE_HOURLY = 'CACHE_HOURLY';
    const CACHE_MINUTELY = 'CACHE_MINUTELY';

    /**
     * @var array
     */
    protected $primaryKeys;

    /**
     * @var string Table name
     */
    protected $table;

    /**
     * @var array Fields can be searched on to directly be HTTP request
     */
    protected $searchFields = [];

    /**
     * @var array Fields used for Measure class to convert automatically
     */
    protected $measureFields = [];

    /**
     * @var array Customs fields can be sort
     */
    protected $customizedSortFields = [];

    /**
     * @var array Customs fields can be sort
     */
    protected $customizedFilterFields = [];

    /**
     * @var string Model name used in this TableGateway
     */
    protected $modelClass;

    /**
     * @var AbstractModel
     */
    protected $modelInstance;
    /**
     * @var Select
     */
    protected $injectedSelect;

    /**
     * @var bool
     */
    protected $updateStatusToDelete = false;

    /**
     * @var Translator
     */
    protected static $translator;

    /**
     * @var null|\Zend\Db\ResultSet\ResultSet|AbstractModel[]
     */
    protected $oldRecords = null;

    /**
     * AbstractTableGateway constructor.
     *
     * @param AdapterInterface $adapter
     */
    public function __construct(AdapterInterface $adapter = null)
    {
        $resultSetPrototype = new ResultSet(ResultSet::TYPE_ARRAYOBJECT, new $this->modelClass);

        $result = parent::__construct($this->table, $adapter, null, $resultSetPrototype);

        $dateTime = new \DateTime();
        $this->getAdapter()->query('SET time_zone = ?',
            [
                $dateTime->format('P')
            ]);

        return $result;
    }

    /**
     * @return \Zend\Db\Adapter\Driver\AbstractConnection|\Zend\Db\Adapter\Driver\ConnectionInterface
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

    /**
     * @param AbstractModel $model
     * @param bool|false    $voidUpsertPreCheck
     *
     * @return array|\ArrayObject|null
     * @throws Exception
     * @throws IncompleteCompositeKeyException
     * @throws SeekException
     */
    public function save(AbstractModel $model, $voidUpsertPreCheck = false)
    {
        $id = $model->getId();

        /** @var AbstractModel $oldModel */
        $oldModel = $this->select($this->decodeCompositeKey($id))->current();
        if (is_null($id) || !$oldModel) {
            $id = $this->saveInsert($model, $voidUpsertPreCheck);

            return $this->get($id);
        } else {
            $this->saveUpdate($model, $oldModel, $voidUpsertPreCheck);

            return $this->get($id);
        }
    }

    /**
     * @param $data array
     *
     * @return AbstractModel
     */
    public function postSave($data)
    {
        /**
         * @var AbstractModel $Model
         */
        $Model         = $this->getModel($data);
        $isUpdate      = false;
        $compositeKeys = $this->decodeCompositeKey($Model->getId());
        /** @var AbstractModel $OldModel */
        if (count($compositeKeys) == count($this->getPrimaryKeys())) {
            if ($OldModel = $this->select($compositeKeys)->current()) {
                $isUpdate = true;
            }
        }

        if ($isUpdate) {
            $Model = $this->getModel($OldModel->getArrayCopy())->exchangeArray($data);
            $this->saveUpdate($Model, $OldModel);
            $Model = $this->get($Model->getId());
        } else {
            $Model->exchangeArray($data);
            $id    = $this->saveInsert($Model);
            $Model = $this->get($id);
        }

        return $Model;
    }

    /**
     * @param AbstractModel $model
     * @param bool|false    $voidUpsertPreCheck
     *
     * @return int|null
     */
    public function saveInsert(AbstractModel $model, $voidUpsertPreCheck = false)
    {
        $id   = null;
        $data = $model->getArrayCopy(false);

        if (isset($data['id']) && empty($data['id'])) {
            $data['id'] = new Expression('DEFAULT');
        }

        $this->insert($data, $voidUpsertPreCheck, $id);

        if ($id) {
            return $id;
        } else {
            if ($model->getId()) {
                return $model->getId();
            } else {
                return $this->getLastInsertValue();
            }
        }
    }


    /**
     * @param AbstractModel $model
     * @param AbstractModel $oldModel
     * @param bool|false    $voidUpsertPreCheck
     *
     * @return int
     * @throws IncompleteCompositeKeyException
     */
    public function saveUpdate(AbstractModel $model, AbstractModel $oldModel, $voidUpsertPreCheck = false)
    {
        return $this->update(
            $model->getArrayCopy(false),
            $this->decodeCompositeKey($model->getId()),
            $voidUpsertPreCheck,
            $oldModel->getArrayCopy(false)
        );
    }

    /**
     * @param $id
     *
     * @return array|\ArrayObject|AbstractModel
     * @throws SeekException
     */
    public function get($id)
    {
        $row = $this->select($this->decodeCompositeKey($id))->current();
        if (!$row) {
            throw new SeekException("Could not find record by ID $id");
        }

        return $row;
    }


    /**
     * @param $keyString
     *
     * @return array
     * @throws IncompleteCompositeKeyException
     */
    public function decodeCompositeKey($keyString)
    {
        if (!$keyString) {
            return [];
        }
        /**
         * @var \BS\Db\Model\AbstractModel $Model
         */
        $Model         = new $this->modelClass;
        $Transformer   = new CaseTransformer(new Format\SnakeCase(), new Format\StudlyCaps());
        $compositeKeys = [];

        $Model->decodeCompositeKey($keyString);

        foreach ($Model->getPrimaryKeys() as $index => $key) {
            $method                                        = 'get' . ucfirst($Transformer->transform($key));
            $compositeKeys[$this->getTable() . '.' . $key] = $Model->$method();
        }

        return $compositeKeys;
    }

    /**
     * @return array
     */
    public function getPrimaryKeys()
    {
        return $this->primaryKeys;
    }

    /**
     * @param array $primaryKeys
     *
     * @return $this
     */
    public function setPrimaryKeys($primaryKeys)
    {
        $this->primaryKeys = $primaryKeys;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isUpdateStatusToDelete()
    {
        return $this->updateStatusToDelete;
    }

    /**
     * @param boolean $updateStatusToDelete
     *
     * @return $this
     */
    public function setUpdateStatusToDelete($updateStatusToDelete)
    {
        $this->updateStatusToDelete = $updateStatusToDelete;

        return $this;
    }

    /**
     * @param array $data
     *
     * @param bool  $voidUpsertPreCheck
     *
     * @param null  $id
     *
     * @return int
     */
    public function insert($data, $voidUpsertPreCheck = false, &$id = null)
    {
        if (!$voidUpsertPreCheck) {
            $this->upsertPreCheck($data);
        }

        $data   = $this->sanitizeData($data);
        $result = parent::insert($data);
        $id     = $this->getLastInsertValue();

        return $result;
    }

    /**
     * @param array               $data
     * @param null                $where
     * @param bool|false          $voidUpsertPreCheck
     * @param array|AbstractModel $oldData
     *
     * @return int
     * @throws AppException
     */
    public function update($data, $where = null, $voidUpsertPreCheck = false, $oldData = [])
    {
        if (!$voidUpsertPreCheck) {
            $this->upsertPreCheck($data, $where, $oldData);
        }

        $data = $this->sanitizeData($data);
        if (count($data) == 0) {
            throw new AppException('No data to update after sanitization');
        }

        $data = Utility::arrayDiff($data, $this->sanitizeData($oldData));

        return $data ? parent::update($data, $where) : 0;
    }

    /**
     * @param       $data
     * @param array $where
     * @param array $oldData
     *
     * @return bool
     * @codeCoverageIgnore
     */
    protected function upsertPreCheck($data, $where = [], $oldData = [])
    {
        $this->log(get_class($this) . ' UpsertPreCheck', ['NEW' => $data, 'WHERE' => $where, 'OLD' => $oldData]);

        return true;
    }

    /**
     * @param AbstractModel $model
     *
     * @return int
     */
    public function destroy(AbstractModel $model)
    {
        return $this->delete($this->decodeCompositeKey($model->getId()));
    }

    /**
     * @param array|\Closure|string|\Zend\Db\Sql\Where $where
     * @param boolean                                  $forceDelete
     *
     * @return int
     */
    public function delete($where, $forceDelete = false)
    {
        $this->deletePreCheck($where);
        if (!$forceDelete && $this->isUpdateStatusToDelete()) {
            $numberOfAffectedRows = $this->update(['status' => 'DELETED'], $where);
        } else {
            $numberOfAffectedRows = parent::delete($where);
        }

        return $numberOfAffectedRows;
    }

    /**
     * Precheck for DELETE query
     *
     * @param array $where
     *
     * @return bool
     */
    protected function deletePreCheck($where = [])
    {
        if ($where instanceof AbstractModel) {
            $where = $where->__toArray();
        }
        $this->log(get_class($this) . ' DeletePreCheck', is_array($where) ? $where : [$where]);

        return true;
    }


    /**
     *
     * Sanitize data before sending to database
     *
     * @param array $data
     *
     * @return array
     */
    public function sanitizeData(array $data)
    {
        // Remove 'id' field when using composite key
        $primaryKeys = $this->getPrimaryKeys();

        // Deal with empty value
        foreach ($data as $key => $value) {
            if (!in_array($key, $primaryKeys) && empty($data[$key]) && !is_numeric($data[$key])) {
                $data[$key] = new Expression('DEFAULT');
            }
        }

        $sanitizedData = [];
        foreach ($this->getColumns() as $column) {
            if (array_key_exists($column, $data)) {
                $sanitizedData[$column] = $data[$column];
            }
        }

        return $sanitizedData;
    }

    /**
     * This method is only supposed to be used by getListAction
     *
     * @param null $limit
     *
     * @return array [ResultSet,int] Returns an array of resultSet,total_found_rows
     */
    public function fetchAll($limit = null)
    {
        if ($this->injectedSelect) {
            $select = $this->injectedSelect;
        } elseif ($limit instanceof Select) {
            $select = $limit;
        } else {
            $select = $this->getSql()->select();
        }

        if ($limit !== null && is_numeric($limit)) {
            $select->limit(intval($limit));
        }

        $resultSet = $this->selectWith($select);

        if ($combineSelect = $select->getRawState(Select::COMBINE)) {
            /** @var Select $combineSelect */
            $combineSelect = $combineSelect['select'];
            $combineSelect->reset(Select::LIMIT)
                ->reset(Select::COLUMNS)
                ->reset(Select::OFFSET)
                ->reset(Select::ORDER)
                ->columns(['line' => new Expression('1')]);
            $joins = $combineSelect->getRawState(Select::JOINS);
            $combineSelect->reset(Select::JOINS);
            foreach ($joins as $join) {
                $combineSelect->join($join['name'], $join['on'], [], $join['type']);
            }
            $select->reset(Select::COMBINE)
                ->combine($combineSelect, 'UNION ALL');
        }

        $quantifierSelect = $select
            ->reset(Select::LIMIT)
            ->reset(Select::COLUMNS)
            ->reset(Select::OFFSET)
            ->reset(Select::ORDER)
            ->columns(['line' => new Expression('1')]);


        //Reset joined tables
        $joins = $quantifierSelect->getRawState(Select::JOINS);
        $quantifierSelect->reset(Select::JOINS);
        foreach ($joins as $join) {
            $quantifierSelect->join($join['name'], $join['on'], [], $join['type']);
        }

        $totalSelect = (new Sql($this->getAdapter()))
            ->select(['ori_query' => $quantifierSelect])
            ->columns(['total' => new Expression('COUNT(*)')]);

        /* execute the select and extract the total */
        $row   = $this->getSql()
            ->prepareStatementForSqlObject($totalSelect)
            ->execute()
            ->current();
        $total = (int)$row['total'];

        return [$resultSet, $total];
    }

    /**
     * @return array|\ArrayObject|null
     * @throws SeekException
     */
    public function fetchRandom()
    {
        $resultSet = $this->select(function (Select $select) {
            $select->order(new Expression('RAND()'))->limit(1);
        });

        if (0 == count($resultSet)) {
            throw new SeekException();
        }

        return $resultSet->current();
    }

    /**
     * @param array|Select $where
     * @param array|string $order
     * @param int          $offset
     *
     * @return array|\ArrayObject|null|\BS\Db\Model\AbstractModel
     * @throws SeekException
     */
    public function fetchRow($where = null, $order = null, $offset = null)
    {
        $columns = [];
        if ($where instanceof Select) {
            $where->limit(1);
            $resultSet = $this->selectWith($where);
            $columns   = $where->getRawState($where::COLUMNS);
        } else {
            $resultSet = $this->select(function (Select $select)
            use ($where, $order, $offset, &$columns) {
                if (!is_null($where)) {
                    $select->where($where);
                }
                if (!is_null($order)) {
                    $select->order($order);
                }
                if (!is_null($offset)) {
                    $select->offset($offset);
                }
                $select->limit(1);
                $columns = $select->getRawState($select::COLUMNS);
            });
        }

        if (0 == count($resultSet)) {
            return null;
        }
        $instance = $resultSet->current();

        if ($instance instanceof AbstractModel && in_array('id', $columns)) {
            $instanceArr = $instance->getArrayCopy();
            $idKey       = is_string(array_search('id', $columns)) ? array_search('id', $columns) : 'id';
            if (is_null($instanceArr[$idKey])) {
                return null;
            }
        }

        return $instance;
    }

    /**
     * @param Select $select
     * @param array  $params
     *
     * @return $this
     */
    public function injectSelect(Select $select, $params = [])
    {
        $this->injectedSelect = $select;
        $this->log(get_class($this) . ' InjectSelect Params:', $params);

        return $this;
    }

    /**
     * @param $model string|AbstractModel
     *
     * @return $this
     */
    public function setModelClass($model)
    {
        switch (true) {
            case is_string($model):
                $this->modelClass = $model;
                break;
            case $model instanceof AbstractModel:
                $this->modelClass = get_class($model);
                break;
            default:
                throw new \LogicException('Unknown model given');
        }

        return $this;
    }

    /**
     * @param array $data
     *
     * @return AbstractModel
     */
    public function getModel($data = [])
    {
        /** @var AbstractModel $AbstractModel */
        $AbstractModel = new $this->modelClass;
        if (!empty($data)) {
            $AbstractModel->exchangeArray($data);
        }

        return $AbstractModel;
    }

    /**
     * @param $table string Table name
     *
     * @return $this
     */
    public function setTable($table)
    {
        if (!is_string($table)) {
            throw new \LogicException('Invlid table name given');
        }
        $this->table = $table;

        return $this;
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param array      $searchFields
     * @param bool|false $isReset
     *
     * @return $this
     */
    public function setSearchFields($searchFields = [], $isReset = false)
    {
        if (!is_array($searchFields)) {
            throw new \InvalidArgumentException;
        }
        if (!$isReset) {
            $this->searchFields = array_merge($this->searchFields, $searchFields);
        } else {
            $this->searchFields = $searchFields;
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getSearchFields()
    {
        return $this->searchFields;
    }

    /**
     * @param array $columns
     *
     * @return $this
     */
    public function setColumns($columns)
    {
        $this->columns = $columns;

        return $this;
    }


    protected function _checkStatusChange($oldValue, $newValue, $stateTransition = [])
    {
        if ($oldValue === $newValue) {
            return;
        }

        if (!array_key_exists($oldValue, $stateTransition)
            || !is_array($stateTransition[$oldValue])
            || !in_array($newValue, $stateTransition[$oldValue])
        ) {
            if (isset($stateTransition[$oldValue]) && is_array($stateTransition[$oldValue]) &&
                count($stateTransition[$oldValue]) > 0
            ) {
                $allowNewStatus = implode(' or ', $stateTransition[$oldValue]);
                throw new Exception(sprintf(
                    $this->t('Record (%s) in %s status can only be changed into %s'),
                    $this->table,
                    $oldValue,
                    $allowNewStatus
                ));
            } else {
                throw new Exception(sprintf(
                    $this->t('Record (%s) in %s status can not be changed any more'),
                    $this->table,
                    $oldValue
                ));
            }
        }
    }

    protected function getOldRecords($where, $forceSelect = false)
    {
        if (is_null($this->oldRecords) || $forceSelect) {
            $this->oldRecords = $this->select($where)->buffer();
        }

        return $this->oldRecords;
    }

    public function getCount($where = [], $group = null)
    {
        $Select = $this->getSql()->select();
        $Select->columns(['total' => new Expression('IFNULL(COUNT(*),0)')])->where($where);
        if (!empty($group)) {
            $Select->group($group);
        }
        $row = $this->getSql()
            ->prepareStatementForSqlObject($Select)
            ->execute()
            ->current();
        if (!is_null($row)) {
            return $row['total'];
        } else {
            return 0;
        }
    }

    public function getMeasureField()
    {
        return $this->measureFields;
    }

    /**
     * @return Select
     */
    public function getInjectedSelect()
    {
        return $this->injectedSelect;
    }

    /**
     * @return array
     */
    public function getCustomizedSortFields()
    {
        return $this->customizedSortFields;
    }

    /**
     * @return array
     */
    public function getCustomizedFilterFields()
    {
        return $this->customizedFilterFields;
    }
}
