<?php
namespace CLI\Model;

use CLI\Model\CodeGenerator\Source\BaseModuleBaseController;
use CLI\Model\CodeGenerator\Source\BaseModuleBaseModel;
use CLI\Model\CodeGenerator\Source\BaseModuleBaseTableGateway;
use CLI\Model\CodeGenerator\Source\BaseModuleSubController;
use CLI\Model\CodeGenerator\Source\BaseModuleSubModel;
use CLI\Model\CodeGenerator\Source\BaseModuleSubTableGateway;
use CLI\Model\CodeGenerator\Source\SubModuleBaseController;
use CLI\Model\CodeGenerator\Source\SubModuleBaseTableGateway;
use CLI\Model\CodeGenerator\Source\SubModuleSubController;
use CLI\Model\CodeGenerator\Source\SubModuleSubModel;
use CLI\Model\CodeGenerator\Source\SubModuleSubTableGateway;
use Zend\Db\Metadata\Object\TableObject;

class SourceCodeGenerator extends AbstractCodeGenerator
{
    // =============== Controllers ===================
    public function generateBaseModuleBaseController(TableObject $table)
    {
        $CodeGenerator = new BaseModuleBaseController($table, $this->config->getBaseModule(),
                                                      $this->config->getBaseModule() . '\\' .
                                                      self::NAMESPACE_CONTROLLER . '\AbstractRestfulController');
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_SOURCE);

        return $CodeGenerator;
    }

    public function generateBaseModuleSubController(TableObject $table)
    {
        $CodeGenerator = new BaseModuleSubController($table, $this->config->getBaseModule(), 'Base');
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_SOURCE);

        return $CodeGenerator;
    }


    public function generateSubModuleBaseController(TableObject $table, $module)
    {
        $CodeGenerator = new SubModuleBaseController($table, $module, $this->config->getBaseModule());
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_SOURCE);

        return $CodeGenerator;
    }

    public function generateSubModuleSubController(TableObject $table, $module)
    {
        $CodeGenerator = new SubModuleSubController($table, $module, 'Base');
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_SOURCE);

        return $CodeGenerator;
    }

    // =============== Models ===================
    public function generateBaseModuleBaseModel(TableObject $table)
    {
        $CodeGenerator = new BaseModuleBaseModel($table, $this->config->getBaseModule(),
                                                 $this->config->getBaseModule() . '\\' . self::NAMESPACE_MODEL .
                                                 '\AbstractModel');
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_SOURCE);

        return $CodeGenerator;
    }

    public function generateBaseModuleSubModel(TableObject $table)
    {
        $CodeGenerator = new BaseModuleSubModel($table, $this->config->getBaseModule(), 'Base');
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_SOURCE);

        return $CodeGenerator;
    }

    public function generateSubModuleSubModel(TableObject $table, $module)
    {
        $CodeGenerator = new SubModuleSubModel($table, $module, $this->config->getBaseModule());
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_SOURCE);

        return $CodeGenerator;
    }

    // =============== TableGateways ===================
    public function generateBaseModuleBaseTableGateway(TableObject $table)
    {
        $CodeGenerator = new BaseModuleBaseTableGateway($table, $this->config->getBaseModule(),
                                                        $this->config->getBaseModule() . '\\' .
                                                        self::NAMESPACE_TABLEGATEWAY . '\AbstractTableGateway');
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_SOURCE);

        return $CodeGenerator;
    }

    public function generateBaseModuleSubTableGateway(TableObject $table)
    {
        $CodeGenerator = new BaseModuleSubTableGateway($table, $this->config->getBaseModule(), 'Base');
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_SOURCE);

        return $CodeGenerator;
    }

    public function generateSubModuleBaseTableGateway(TableObject $table, $module)
    {
        $CodeGenerator = new SubModuleBaseTableGateway($table, $module, $this->config->getBaseModule());
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_SOURCE);

        return $CodeGenerator;
    }

    public function generateSubModuleSubTableGateway(TableObject $table, $module)
    {
        $CodeGenerator = new SubModuleSubTableGateway($table, $module, 'Base');
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_SOURCE);

        return $CodeGenerator;
    }
}