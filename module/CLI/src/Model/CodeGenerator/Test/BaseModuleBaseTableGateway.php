<?php
namespace CLI\Model\CodeGenerator\Test;

use Camel\CaseTransformer;
use Camel\Format\SnakeCase;
use Camel\Format\StudlyCaps;
use CLI\Model\AbstractCodeGenerator;
use CLI\Model\CodeGenerator\AbstractFileGenerator;
use CLI\Model\CodeGenerator\BaseWarningTrait;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlock\Tag\GenericTag;
use Zend\Code\Generator\DocBlock\Tag\ParamTag;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\PropertyGenerator;
use Zend\Code\Generator\PropertyValueGenerator;
use Zend\Db\Metadata\Object\ColumnObject;
use Zend\Db\Metadata\Object\TableObject;

class BaseModuleBaseTableGateway extends AbstractFileGenerator
{
    use BaseWarningTrait;

    protected $overwriteMode = self::OVERWRITE_MODE_ON;

    protected $expects = [];

    public function __construct(TableObject $table, $module = null, $parentClass = null)
    {
        parent::__construct();

        $this->table = $table;
        $Transformer = new CaseTransformer(new SnakeCase(), new StudlyCaps());
        $className   = $Transformer->transform($this->table->getName()) . 'Test';
        $this->setFilename($className);

        $ClassGenerator = new ClassGenerator($className);
        $ClassGenerator->setFlags(ClassGenerator::FLAG_ABSTRACT);

        if ($module) {
            $this->setNamespace($module . '\\' . AbstractCodeGenerator::NAMESPACE_TEST . '\\' .
                                AbstractCodeGenerator::NAMESPACE_TABLEGATEWAY . '\Base');
        }

        if ($parentClass) {
            $this->setUse($parentClass, 'BaseClass');
            $ClassGenerator->setExtendedClass('BaseClass');
        }

        $this->setUse($module . '\\' .
                      AbstractCodeGenerator::NAMESPACE_TABLEGATEWAY . '\\' .
                      $Transformer->transform($this->table->getName()), 'TableGateway');
        $this->setUse($module . '\\' . AbstractCodeGenerator::NAMESPACE_MODEL . '\\' .
                      $Transformer->transform($this->table->getName()), 'Model');

        $this->setUse($module . '\\' . AbstractCodeGenerator::NAMESPACE_TEST . '\Traits\AuthenticationTrait',
            'AuthenticationTrait');

        $this->setUse('Zend\Db\Sql\Expression');

        $ClassGenerator->addTrait('AuthenticationTrait');

        $ClassGenerator->setDocBlock(new DocBlockGenerator(null, null, [
            new GenericTag('method', 'TableGateway getTableGateway()'),
            new GenericTag('method', 'Model getModelInstance(array $data = [], $forceCreate = false)'),
        ]));

        $ClassGenerator->addProperty('tableGatewayClass',
            new PropertyValueGenerator('TableGateway::class',
                PropertyValueGenerator::TYPE_CONSTANT),
            PropertyGenerator::FLAG_PROTECTED);

        $ClassGenerator->addProperty('modelClass',
            new PropertyValueGenerator('Model::class', PropertyValueGenerator::TYPE_CONSTANT),
            PropertyGenerator::FLAG_PROTECTED);

        $ClassGenerator->addMethod('initModelInstance', [
            ['name' => 'data', 'type' => 'array', 'defaultvalue' => []],
            ['name' => 'autoSave', 'defaultvalue' => false],
        ], MethodGenerator::FLAG_PUBLIC, $this->initModelInstance(),
            new DocBlockGenerator(null, null, [
                new ParamTag('data', ['[]']),
                new ParamTag('autoSave', ['bool']),
                new ReturnTag('Model'),
            ]));

        $this->setBody($this->getBaseWarningTrait() . str_repeat(self::LINE_FEED, 2) . $ClassGenerator->generate());
    }

    protected function initModelInstance()
    {
        $initModelInstanceContent = '';
        /** @var ColumnObject[] $columns */
        $columns = $this->table->getColumns();
        foreach ($columns as $Column) {
            $expected = $this->getExpectedValue($Column);
            $initModelInstanceContent .= $this->getIndentation() . "'{$Column->getName()}' => {$expected}," .
                                         self::LINE_FEED;
        }

        $content =
            '$faker = $this->getFaker();' . self::LINE_FEED .
            '$testData = [' . self::LINE_FEED .
            $initModelInstanceContent .
            '];' . self::LINE_FEED . self::LINE_FEED .
            '$data = array_merge($testData, $data);' . self::LINE_FEED .
            'return parent::initModelInstance($data, $autoSave);';

        return $content;
    }

    protected function getExpectedValue(ColumnObject $Column)
    {
        if (isset($this->expects[$Column->getName()])) {
            return $this->expects[$Column->getName()];
        }
        $field         = $Column->getName();
        $typeFragments = explode(' ', $Column->getDataType());
        $type          = reset($typeFragments);

        switch (true) {
            // Special Cases
            case 'id' == substr($field, -2, 2):
                $expected = "new Expression('DEFAULT')";
                if ('id' == $field) {
                    $expected = 'null';
                }
                break;
            case (false !== strpos($field, 'contact')):
                $expected = "\$faker->firstName . ' ' . \$faker->lastName";
                break;
            case 'first_name' == $field:
                $expected = '$faker->firstName';
                break;
            case 'last_name' == $field:
                $expected = '$faker->lastName';
                break;
            case 'email' == $field:
                $expected = '$faker->email';
                break;
            case 'username' == $field:
                $expected = '$faker->username';
                break;
            case 'password' == $field:
                $expected = 'md5($faker->password)';
                break;
            case 'qq' == $field;
                $expected = '$faker->numerify(\'###########\')';
                break;
            case 'telephone' == $field;
                $expected = '$faker->phoneNumber';
                break;
            case 'company_name' == $field;
                $expected = '$faker->company';
                break;
            case (false !== strpos($field, 'address_line1')):
                $expected = "\$faker->buildingNumber . ' ' . \$faker->streetName";
                break;
            case (false !== strpos($field, 'address_line2')):
                $expected = '$faker->secondaryAddress';
                break;
            case (false !== strpos($field, 'address_line3')):
                $expected = "''";
                break;
            case (false !== strpos($field, 'city')):
                $expected = '$faker->city';
                break;
            case (false !== strpos($field, 'county')):
                $expected = '$faker->county';
                break;
            case (false !== strpos($field, 'post_code'));
                $expected = '$faker->postcode';
                break;
            case (false !== strpos($field, 'country_iso')):
                $expected = "'GB'";
                break;
            case (false !== strpos($field, 'reference')):
                $expected = "\$faker->lexify('??????????')";
                break;
            case (false !== strpos($field, '_url')):
                $expected = '$faker->url';
                break;
            case 'limitation_formula' == $field:
                $expected = "'{length>0}'";
                break;
            case (false !== strpos($field, '_restriction_')) ||
                 'suffix' == $field;
                $expected = '$faker->fileExtension';
                break;

            // Default types
//            case (false !== strpos($type, 'int')) ||
//                 (false !== strpos($type, 'float')) ||
//                 (false !== strpos($type, 'double')) ||
//                 (false !== strpos($type, 'decimal')) ||
//                 (false !== strpos($type, 'numeric')):
//                $expected = "new Expression('DEFAULT')";
//                break;
            case 'varchar' == $type:
                $expected = '$faker->words(3,true)';
                break;
            case 'text' == $type:
                $expected = '$faker->paragraph';
                break;
            case 'data' == $type:
                $expected = '$faker->data()';
                break;
            case 'time' == $type:
                $expected = '$faker->time()';
                break;
            case 'timestamp' == $type:
                $expected = "\$faker->dateTimeBetween('-1 years','now')";
                break;
            case substr($type, 0, strlen('enum')) == 'enum':
                $expected = "new Expression('DEFAULT')";
                break;

            default:
                $expected = "'" . $Column->getColumnDefault() . "'";
                break;
        }


        $this->expects[$Column->getName()] = $expected;

        return $expected;
    }
}