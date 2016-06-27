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
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\PropertyGenerator;
use Zend\Code\Generator\PropertyValueGenerator;
use Zend\Db\Metadata\Object\TableObject;

class SubModuleBaseTableGateway extends AbstractFileGenerator
{
    use BaseWarningTrait;

    protected $overwriteMode = self::OVERWRITE_MODE_ON;

    public function __construct(TableObject $table, $module = null, $parentClassNamespace = null)
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

        if ($parentClassNamespace) {
            $this->setUse($parentClassNamespace . '\\' . AbstractCodeGenerator::NAMESPACE_TEST . '\\'
                          . AbstractCodeGenerator::NAMESPACE_TABLEGATEWAY . '\\' . $className, 'BaseClass');
            $ClassGenerator->setExtendedClass('BaseClass');
        }

        $this->setUse($module . '\\' . AbstractCodeGenerator::NAMESPACE_TABLEGATEWAY . '\\' .
                      $Transformer->transform($this->table->getName()), 'TableGateway');
        $this->setUse($module . '\\' . AbstractCodeGenerator::NAMESPACE_MODEL . '\\' .
                      $Transformer->transform($this->table->getName()), 'Model');

        $ClassGenerator->addTrait('AuthenticationTrait');

        $this->setUse($module . '\\' . AbstractCodeGenerator::NAMESPACE_TEST . '\Traits\AuthenticationTrait',
            'AuthenticationTrait');

        $ClassGenerator->setDocBlock(new DocBlockGenerator(null, null, [
            new GenericTag('method', 'TableGateway getTableGateway()'),
            new GenericTag('method', 'Model initModelInstance(array $data = [], $autoSave = false)'),
            new GenericTag('method', 'Model getModelInstance(array $data = [], $forceCreate = false)'),
        ]));

        $ClassGenerator->addProperty('tableGatewayClass',
                                     new PropertyValueGenerator('TableGateway::class',
                                                                PropertyValueGenerator::TYPE_CONSTANT),
                                     PropertyGenerator::FLAG_PROTECTED);

        $ClassGenerator->addProperty('modelClass',
                                     new PropertyValueGenerator('Model::class', PropertyValueGenerator::TYPE_CONSTANT),
                                     PropertyGenerator::FLAG_PROTECTED);

        $this->setBody($this->getBaseWarningTrait() . str_repeat(self::LINE_FEED, 2) . $ClassGenerator->generate());
    }
}