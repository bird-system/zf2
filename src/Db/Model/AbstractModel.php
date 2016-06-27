<?php
namespace BS\Db\Model;

use BS\Db\Model\Exception\IncompleteCompositeKeyException;
use BS\Exception;
use Camel\CaseTransformer;
use Camel\Format;
use Zend\Db\Sql\Expression;
use Zend\Stdlib\ArraySerializableInterface;

abstract class AbstractModel implements ArraySerializableInterface
{
    const COMPOSITE_KEY_DELIMITER = '_';

    /**
     * Declare Primary Key in order so we can parse the values into getId()
     *
     * @var array
     */
    protected $primaryKeys = [];

    protected $disallowedPropertyList = [];

    protected $extraFields = [];
    protected $extraData = [];

    public function __construct(array $data = [])
    {
        if ($data) {
            $this->exchangeArray($data);
        }
    }

    /**
     * @return bool
     */
    public static function hasCompositeKey()
    {
        return false;
    }

    /**
     * @param bool $transExtraData
     *
     * @return array
     */
    public function getArrayCopy($transExtraData = true)
    {
        return $this->__toArray($transExtraData);
    }

    public function __get($key)
    {
        if (in_array($key, $this->extraFields)) {
            return isset($this->extraData[$key]) ? $this->extraData[$key] : null;
        } else {
            throw new Exception("property '$key' not existing");
        }
    }

    public function __set($key, $value)
    {
        if (in_array($key, $this->extraFields)) {
            $this->extraData[$key] = $value;
        }
    }

    /**
     * @param array $data
     *
     * @return AbstractModel $this
     */
    public function exchangeArray(array $data)
    {
        $this->extraData        = [];
        $disallowedPropertyList = $this->getDisallowedProperties();
        $transformer            = new CaseTransformer(new Format\SnakeCase(), new Format\StudlyCaps());
        foreach ($data as $key => $value) {
            $method = 'set' . ucfirst($transformer->transform($key));
            if (method_exists($this, $method) && !in_array($key, $disallowedPropertyList)) {
                $this->$method($value);
            }
            if (in_array($key, $this->extraFields)) {
                $this->extraData[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * @param bool $transExtraData
     *
     * @return array
     */
    public function __toArray($transExtraData = true)
    {
        $array                  = [];
        $disallowedPropertyList = $this->getDisallowedProperties();

        $transformer = new CaseTransformer(new Format\StudlyCaps(), new Format\SnakeCase());
        foreach (get_class_methods($this) as $key => $value) {
            if (0 === strpos($value, 'get') && !in_array(substr($value, 3), $disallowedPropertyList)) {
                $currentValue = $this->$value();
                switch (true) {
                    case $currentValue instanceof \DateTime:
                        $currentValue = $currentValue->format('Y-m-d H:i:s');
                        break;
                }
                $array[$transformer->transform(substr($value, 3))] = $currentValue;
            }
        }

        if ($transExtraData) {
            return array_merge($array, $this->extraData);
        } else {
            return $array;
        }
    }

    public function getDisallowedProperties()
    {
        $disallowedProperties   = $this->disallowedPropertyList;
        $disallowedProperties[] = 'ServiceLocator';
        $disallowedProperties[] = 'DisallowedProperties';
        $disallowedProperties[] = 'ArrayCopy';
        $disallowedProperties[] = 'PrimaryeKeys';
        $disallowedProperties[] = 'Extra';
        $disallowedProperties[] = 'ExtraFields';
        $disallowedProperties[] = 'ExtraField';

        return $disallowedProperties;
    }

    /**
     *
     * @param $valueString
     *
     * @return $this
     * @throws IncompleteCompositeKeyException
     */
    public function decodeCompositeKey($valueString)
    {
        $values = explode(self::COMPOSITE_KEY_DELIMITER, $valueString);

        if (count($values) < count($this->getPrimaryKeys())) {
            throw new IncompleteCompositeKeyException;
        }

        $transformer = new CaseTransformer(new Format\SnakeCase(), new Format\StudlyCaps());

        if (1 == count($this->getPrimaryKeys())) {
            $this->setId($valueString);

            return $this;
        }

        foreach ($this->getPrimaryKeys() as $index => $key) {
            $method = 'set' . ucfirst($transformer->transform($key));
            $this->$method($values[$index]);
        }

        return $this;
    }

    /**
     * @return string
     * @throws IncompleteCompositeKeyException
     */
    public function encodeCompositeKey()
    {
        $orderedValues = [];
        $transformer   = new CaseTransformer(new Format\SnakeCase(), new Format\StudlyCaps());

        foreach ($this->getPrimaryKeys() as $index => $key) {
            $method = 'get' . ucfirst($transformer->transform($key));
            if (!method_exists($this, $method)) {
                throw new IncompleteCompositeKeyException();
            }
            if ($this->$method() instanceof Expression) {
                $orderedValues[] = $this->$method()->getExpression();
            } else {

                $orderedValues[] = $this->$method();
            }
        }

        return implode(self::COMPOSITE_KEY_DELIMITER, $orderedValues);
    }

    /**
     * @param array $keys
     *
     * @return $this
     */
    public function setPrimaryKeys(array $keys)
    {
        $this->primaryKeys = $keys;

        return $this;
    }

    /**
     * @param $field
     * @param $data
     *
     * @return $this
     * @throws Exception
     */
    public function setExtra($field, $data)
    {
        if (!in_array($field, $this->extraFields)) {
            throw new Exception(sprintf('Unknown field [%s]', $field));
        }
        $this->extraData[$field] = $data;

        return $this;
    }

    /**
     * @param $field
     *
     * @return null
     * @throws Exception
     */
    public function getExtra($field)
    {
        if (!in_array($field, $this->extraFields)) {
            throw new Exception(sprintf('Unknown field [%s]', $field));
        }

        return array_key_exists($field, $this->extraData) ? $this->extraData[$field] : null;
    }

    /**
     * @return array
     */
    public function getExtraFields()
    {
        return $this->extraFields;
    }

    /**
     * @param      $extraField
     * @param null $extraData
     *
     * @return $this
     * @throws Exception
     * @internal param array $extraFields
     *
     */
    public function setExtraField($extraField, $extraData = null)
    {
        if (!isset($this->extraFields, $extraField)) {
            array_push($this->extraFields, $extraField);
        }

        if ($extraData) {
            $this->setExtra($extraField, $extraData);
        }

        return $this;
    }


    /**
     * @return array
     */
    public function getPrimaryKeys()
    {
        return (array)$this->primaryKeys;
    }

    abstract public function getId();

    abstract public function setId($id);
}
