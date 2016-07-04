<?php
namespace CLI\Model\CodeGenerator\Source;

use Camel\CaseTransformer;
use Camel\Format\SnakeCase;
use Camel\Format\StudlyCaps;
use CLI\Model\AbstractCodeGenerator;
use CLI\Model\CodeGenerator\AbstractFileGenerator;
use CLI\Model\CodeGenerator\BaseWarningTrait;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\PropertyGenerator;
use Zend\Db\Metadata\Object\ColumnObject;
use Zend\Db\Metadata\Object\ConstraintObject;
use Zend\Db\Metadata\Object\TableObject;

class BaseModuleBaseModel extends AbstractFileGenerator
{
    use BaseWarningTrait;

    protected $overwriteMode = self::OVERWRITE_MODE_ON;

    public function __construct(TableObject $table, $module = null, $parentClass = null)
    {
        parent::__construct();

        $this->table = $table;
        $Transformer = new CaseTransformer(new SnakeCase(), new StudlyCaps());
        $className   = $Transformer->transform($this->table->getName());
        $this->setFilename($className);

        $ClassGenerator = new ClassGenerator($className);
        $ClassGenerator->setFlags(ClassGenerator::FLAG_ABSTRACT);

        if ($module) {
            $this->setNamespace($module . '\\' . AbstractCodeGenerator::NAMESPACE_MODEL . '\Base');
        }

        if ($parentClass) {
            $this->setUse($parentClass, 'BaseClass');
            $ClassGenerator->setExtendedClass('BaseClass');
        }


        /** @var ConstraintObject[] $constraints */
        $constraints = $table->getConstraints();
        $primaryKeys = [];
        foreach ($constraints as $constraint) {
            if ($constraint->isPrimaryKey()) {
                $primaryKeys = $constraint->getColumns();
                break;
            }
        }
        $ClassGenerator->addProperty('primaryKeys', $primaryKeys, PropertyGenerator::FLAG_PROTECTED);

        if (count($primaryKeys) > 1) {
            $ClassGenerator->addMethod('getId',
                [],
                MethodGenerator::FLAG_PUBLIC,
                'return $this->encodeCompositeKey();'
            );

            $ClassGenerator->addMethod('setId',
                ['id'],
                MethodGenerator::FLAG_PUBLIC,
                'return $this->decodeCompositeKey($id);'
            );
        } else {
            if (!isset($primaryKeys[0])) {
                echo sprintf("Table [%s] haven't primary key.\n", $this->table->getName());
            } elseif ($primaryKeys[0] != 'id') {
                $ClassGenerator->addMethod('getId',
                    [],
                    MethodGenerator::FLAG_PUBLIC,
                    'return $this->get' . ucfirst($Transformer->transform($primaryKeys[0])) .
                    '();'
                );

                $ClassGenerator->addMethod('setId',
                    ['id'],
                    MethodGenerator::FLAG_PUBLIC,
                    '$this->set' . ucfirst($Transformer->transform($primaryKeys[0])) .
                    '($id) ;' . self::LINE_FEED .
                    'return $this;'
                );
            }
        }

        /** @var ColumnObject[] $columns */
        $columns = $table->getColumns();
        foreach ($columns as $Column) {
            $columnNameCamelFormat = $Transformer->transform($Column->getName());

            $ClassGenerator->addProperty($Column->getName(), null, PropertyGenerator::FLAG_PROTECTED);
            $ClassGenerator->addMethod('get' . ucfirst($columnNameCamelFormat),
                [],
                MethodGenerator::FLAG_PUBLIC,
                'return $this->' . $Column->getName() . ';'
            );
            $ClassGenerator->addMethod('set' . ucfirst($columnNameCamelFormat),
                [$columnNameCamelFormat],
                MethodGenerator::FLAG_PUBLIC,
                "\$this->{$Column->getName()} = \${$columnNameCamelFormat};" . self::LINE_FEED .
                'return $this;'
            );
        }


        $this->setBody($this->getBaseWarningTrait() . str_repeat(self::LINE_FEED, 2) . $ClassGenerator->generate());
    }
}