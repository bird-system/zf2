<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 20/01/2016
 * Time: 21:15
 */

namespace CLI\Model\CodeGenerator\Test;


use Camel\CaseTransformer;
use Camel\Format\SnakeCase;
use Camel\Format\StudlyCaps;
use CLI\Model\AbstractCodeGenerator;
use CLI\Model\CodeGenerator\AbstractFileGenerator;
use CLI\Model\CodeGenerator\BaseWarningTrait;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlock\Tag\GenericTag;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\PropertyGenerator;
use Zend\Code\Generator\PropertyValueGenerator;
use Zend\Db\Metadata\Object\ColumnObject;
use Zend\Db\Metadata\Object\TableObject;

class BaseModuleBaseModel extends AbstractFileGenerator
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
                                AbstractCodeGenerator::NAMESPACE_MODEL . '\Base');
        }

        if ($parentClass) {
            $this->setUse($parentClass, 'BaseClass');
            $ClassGenerator->setExtendedClass('BaseClass');
        }

        $this->setUse($module . '\\' . AbstractCodeGenerator::NAMESPACE_MODEL . '\\' .
                      $Transformer->transform($this->table->getName()), 'Model');

        $ClassGenerator->addMethod('testExchangeArray', [], MethodGenerator::FLAG_PUBLIC, $this->testExchangeArray());
        $ClassGenerator->addMethod('testSetters', [], MethodGenerator::FLAG_PUBLIC, $this->testSetters(), new
        DocBlockGenerator(null, null, [
            new ReturnTag('Model'),
        ]));
        $ClassGenerator->addMethod('testGetters', [
            ['name' => 'instance', 'type' => 'Model'],
        ], MethodGenerator::FLAG_PUBLIC, $this->testSetters(), new DocBlockGenerator(null, null, [
            new GenericTag('depends', 'testSetters'),
            new GenericTag('var', 'Model $instance'),
            new ReturnTag('Model'),
        ]));
        $ClassGenerator->addMethod('testToArray', [
            ['name' => 'instance', 'type' => 'Model'],
        ], MethodGenerator::FLAG_PUBLIC, $this->testToArray(), new DocBlockGenerator(null, null, [
            new GenericTag('depends', 'testGetters'),
            new GenericTag('var', 'Model $instance'),
        ]));
        $ClassGenerator->addProperty('modelClass',
            new PropertyValueGenerator('Model::class', PropertyValueGenerator::TYPE_CONSTANT),
            PropertyGenerator::FLAG_PROTECTED);
        $this->setBody($this->getBaseWarningTrait() . str_repeat(self::LINE_FEED, 2) . $ClassGenerator->generate());
    }

    protected function getExpectedValue(ColumnObject $Column)
    {
        if (isset($this->expects[$Column->getName()])) {
            return $this->expects[$Column->getName()];
        }
        $field         = $Column->getName();
        $typeFragments = explode(' ', $Column->getDataType());
        $type          = reset($typeFragments);

        if (substr($field, -2, 2) == 'id') {
            $expected = 1;
            if ($field == 'id') {
                $expected = 'null';
            }
        } elseif ($field == 'country_iso') {
            $expected = "'GB'";
        } elseif (substr($type, 0, strlen('int')) == 'int') {
            $expected = 1;
        } elseif ($type == 'timestamp') {
            $expected = "\$this->faker->dateTimeBetween('-1 years')->format('DATE_W3C')";
        } elseif ($type == 'decimal') {
            $expected = "\$this->faker->randomFloat()";
        } elseif (substr($type, 0, strlen('enum')) == 'enum') {
            $expected = "new \\Zend\\Db\\Sql\\Expression('DEFAULT')";
        } else {
            $expected = "'" . $field . "'";
        }

        $this->expects[$Column->getName()] = $expected;

        return $expected;
    }

    protected function testExchangeArray()
    {
        $exchangeArrayContent = '';
        /** @var ColumnObject[] $columns */
        $columns = $this->table->getColumns();
        foreach ($columns as $Column) {
            $expected = $this->getExpectedValue($Column);
            $exchangeArrayContent .= $this->getIndentation() . "'{$Column->getName()}' => {$expected}," .
                                     self::LINE_FEED;
        }

        $content =
            '$instance = new Model();' . self::LINE_FEED .
            '$exchangeArray = [' . self::LINE_FEED .
            $exchangeArrayContent .
            '];' . self::LINE_FEED .
            '$instance->exchangeArray($exchangeArray);' . self::LINE_FEED .
            '$this->assertArraySubset($exchangeArray, $instance->getArrayCopy());';

        return $content;
    }

    protected function testSetters()
    {
        $Transformer = new CaseTransformer(new SnakeCase(), new StudlyCaps());
        $content     = '$instance = new Model();' . self::LINE_FEED;

        /** @var ColumnObject[] $columns */
        $columns = $this->table->getColumns();
        foreach ($columns as $Column) {
            $expected = $this->getExpectedValue($Column);

            $content .= '$this->assertInstanceOf(Model::class, $instance->set' .
                        $Transformer->transform($Column->getName()) .
                        "({$expected}));" . self::LINE_FEED;
        }
        $content .= 'return $instance;';

        return $content;
    }

    protected function testToArray()
    {
        $content = '$data = $instance->getArrayCopy();' . self::LINE_FEED;

        /** @var ColumnObject[] $columns */
        $columns = $this->table->getColumns();
        foreach ($columns as $Column) {
            $typeFragments = explode(' ', $Column->getDataType());
            $type          = reset($typeFragments);
            if (in_array($type, ['timestamp', 'decimal'])) {
                continue;
            }
            $expected = $this->getExpectedValue($Column);
            $content .= "\$this->assertEquals({$expected}, \$data['" . $Column->getName() . "']);" . self::LINE_FEED;
        }

        return $content;
    }

}