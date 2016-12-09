<?php
namespace CLI\Model\CodeGenerator\Source;

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
        $className   = $Transformer->transform($this->table->getName());
        $this->setFilename($className);

        $ClassGenerator = new ClassGenerator($className);

        $ClassGenerator->setFlags(ClassGenerator::FLAG_ABSTRACT);
        if ($module) {
            $this->setNamespace($module . '\\' . AbstractCodeGenerator::NAMESPACE_TABLEGATEWAY . '\Base');
        }

        if ($parentClassNamespace) {
            $this->setUse($parentClassNamespace . '\\' . AbstractCodeGenerator::NAMESPACE_TABLEGATEWAY . '\\' .
                          $className, 'BaseClass');
            $ClassGenerator->setExtendedClass('BaseClass');
        }

        $this->setUse($module . '\\' . AbstractCodeGenerator::NAMESPACE_MODEL . '\\' . $className, 'Model');
        $this->setUses([
            'Zend\Db\ResultSet\ResultSet',
            'Zend\Db\Sql\Select'
        ]);

        $ClassGenerator->setDocBlock(new DocBlockGenerator(null, null, [
            new GenericTag('method', 'Model get($id)'),
            new GenericTag('method', 'Model fetchRandom()'),
            new GenericTag('method', 'Model fetchRow($where = null, $order = null, $offset = null)'),
            new GenericTag('method', 'Model save(Model $model, $voidUpsertPreCheck = false)'),
            new GenericTag('method', 'Model[]|ResultSet selectWith(Select $select)'),
            new GenericTag('method', 'Model[]|ResultSet select($where = null)'),
            new GenericTag('method', 'null|Model[]|ResultSet getOldRecords($where, $forceSelect = false)'),
        ]));
        $ClassGenerator->addProperty('modelClass',
            new PropertyValueGenerator(
                'Model::class',
                PropertyValueGenerator::TYPE_CONSTANT),
            PropertyGenerator::FLAG_PROTECTED
        );

        $this->setUse($module . '\Traits\AuthenticationTrait');
        $ClassGenerator->addTrait('AuthenticationTrait');

        $this->setBody($this->getBaseWarningTrait() . str_repeat(self::LINE_FEED, 2) . $ClassGenerator->generate());
    }
}