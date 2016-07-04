<?php
namespace CLI\Model;

use CLI\Model\CodeGenerator\Test\BaseModuleBaseController;
use CLI\Model\CodeGenerator\Test\BaseModuleBaseModel;
use CLI\Model\CodeGenerator\Test\BaseModuleBaseTableGateway;
use CLI\Model\CodeGenerator\Test\BaseModuleSubController;
use CLI\Model\CodeGenerator\Test\BaseModuleSubModel;
use CLI\Model\CodeGenerator\Test\BaseModuleSubTableGateway;
use CLI\Model\CodeGenerator\Test\SubModuleBaseController;
use CLI\Model\CodeGenerator\Test\SubModuleBaseTableGateway;
use CLI\Model\CodeGenerator\Test\SubModuleSubController;
use CLI\Model\CodeGenerator\Test\SubModuleSubModel;
use CLI\Model\CodeGenerator\Test\SubModuleSubTableGateway;
use Zend\Db\Metadata\Object\TableObject;

class TestCodeGenerator extends AbstractCodeGenerator
{
    // =============== Controllers ===================
    public function generateBaseModuleBaseController(TableObject $table)
    {
        $CodeGenerator = new BaseModuleBaseController($table, $this->config->getBaseModule(),
            $this->config->getBaseModule() . '\\' . self::NAMESPACE_TEST .
            '\\' . self::NAMESPACE_CONTROLLER .
            '\Abstract' . $this->config->getBaseModule() . 'HttpControllerTestCase');
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_TEST);

        return $CodeGenerator;
    }

    public function generateBaseModuleSubController(TableObject $table)
    {
        $CodeGenerator = new BaseModuleSubController($table, $this->config->getBaseModule(), 'Base');
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_TEST);

        return $CodeGenerator;
    }


    public function generateSubModuleBaseController(TableObject $table, $module)
    {
        $CodeGenerator = new SubModuleBaseController($table, $module, $this->config->getBaseModule());
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_TEST);

        return $CodeGenerator;
    }

    public function generateSubModuleSubController(TableObject $table, $module)
    {
        $CodeGenerator = new SubModuleSubController($table, $module, 'Base');
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_TEST);

        return $CodeGenerator;
    }

    // =============== Models ===================
    public function generateBaseModuleBaseModel(TableObject $table)
    {
        $CodeGenerator = new BaseModuleBaseModel($table, $this->config->getBaseModule(),
            $this->config->getBaseModule() . '\\' . self::NAMESPACE_TEST .
            '\\' . self::NAMESPACE_MODEL .
            '\AbstractModelTest');
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_TEST);

        return $CodeGenerator;
    }

    public function generateBaseModuleSubModel(TableObject $table)
    {
        $CodeGenerator = new BaseModuleSubModel($table, $this->config->getBaseModule(), 'Base');
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_TEST);

        return $CodeGenerator;
    }

    public function generateSubModuleSubModel(TableObject $table, $module)
    {
        $CodeGenerator = new SubModuleSubModel($table, $module, $this->config->getBaseModule());
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_TEST);

        return $CodeGenerator;
    }

    // =============== TableGateways ===================
    public function generateBaseModuleBaseTableGateway(TableObject $table)
    {
        $CodeGenerator = new BaseModuleBaseTableGateway($table, $this->config->getBaseModule(),
            $this->config->getBaseModule() . '\\' . self::NAMESPACE_TEST .
            '\\' . self::NAMESPACE_TABLEGATEWAY .
            '\AbstractTableGatewayTest');
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_TEST);

        return $CodeGenerator;
    }

    public function generateBaseModuleSubTableGateway(TableObject $table)
    {
        $CodeGenerator = new BaseModuleSubTableGateway($table, $this->config->getBaseModule(), 'Base');
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_TEST);

        return $CodeGenerator;
    }

    public function generateSubModuleBaseTableGateway(TableObject $table, $module)
    {
        $CodeGenerator = new SubModuleBaseTableGateway($table, $module, $this->config->getBaseModule());
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_TEST);

        return $CodeGenerator;
    }

    public function generateSubModuleSubTableGateway(TableObject $table, $module)
    {
        $CodeGenerator = new SubModuleSubTableGateway($table, $module, 'Base');
        $CodeGenerator->setBasePath($this->getConfig()->getCodeBasePath());
        $CodeGenerator->setCodeDirectoryName(self::DIRECTORY_TEST);

        return $CodeGenerator;
    }
}