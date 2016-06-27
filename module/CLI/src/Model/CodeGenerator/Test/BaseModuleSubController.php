<?php
namespace CLI\Model\CodeGenerator\Test;

use Camel\CaseTransformer;
use Camel\Format\SnakeCase;
use Camel\Format\StudlyCaps;
use CLI\Model\AbstractCodeGenerator;
use CLI\Model\CodeGenerator\AbstractFileGenerator;
use Zend\Code\Generator\ClassGenerator;
use Zend\Db\Metadata\Object\TableObject;

class BaseModuleSubController extends AbstractFileGenerator
{
    public function __construct(TableObject $table, $module = null, $parentClassNamespace = null)
    {
        parent::__construct();

        $this->table = $table;
        $Transformer = new CaseTransformer(new SnakeCase(), new StudlyCaps());
        $className   = $Transformer->transform($this->table->getName()) . 'ControllerTest';
        $this->setFilename($className);

        $ClassGenerator = new ClassGenerator($className);
        $ClassGenerator->setFlags(ClassGenerator::FLAG_ABSTRACT);

        if ($module) {
            $this->setNamespace($module . '\\' . AbstractCodeGenerator::NAMESPACE_TEST . '\\' .
                                AbstractCodeGenerator::NAMESPACE_CONTROLLER);
        }
        if ($parentClassNamespace) {
            $this->setUse($this->getNamespace() . '\\' . $parentClassNamespace . '\\' . $className, 'BaseClass');
            $ClassGenerator->setExtendedClass('BaseClass');
        }

        $this->setBody($ClassGenerator->generate());
    }

}