<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 20/01/2016
 * Time: 20:26
 */

namespace CLI\Model;


use Zend\Stdlib\ArrayObject;

class CodeGeneratorConfiguration extends ArrayObject
{
    const TABLE_WHITELIST = 'whitelist';
    const TABLE_BLACKLIST = 'blacklist';

    public function getCodeBasePath()
    {
        return realpath($this->storage['code_base_path']);
    }

    public function getModules()
    {
        return $this->storage['modules'];
    }

    public function getBaseModule()
    {
        return $this->storage['base_module'];
    }

    public function getBaseModuleSourcePath()
    {
        return $this->getModuleSourcePath($this->getBaseModule());
    }

    public function getModuleSourcePath($module)
    {
        return realpath($this->getCodeBasePath() . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . '/src/');
    }

    public function getBaseModuleTestPath()
    {
        return $this->getModuleTestPath($this->getBaseModule());
    }

    public function getModuleTestPath($module)
    {
        return realpath($this->getCodeBasePath() . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . '/tests/');
    }

    public function addModule($module, $tables = null)
    {
        if (!isset($this->storage['modules']) || !is_array($this->storage['modules'])) {
            $this->storage['modules'] = [];
        }

        if (!isset($this->storage['modules'][$module])) {
            $this->storage['modules'][$module] = [];
        }
        if (is_array($tables)) {
            $this->storage['modules'][$module][self::TABLE_WHITELIST] = $tables;
        }

        return $this;
    }

    public function removeModule($module)
    {
        unset($this->storage['modules'][$module]);

        return $this;
    }

    public function getModuleTables($module, $tableNames = [])
    {
        if (!isset($this->storage['modules'][$module])) {
            return [];
        }

        // Filter BlackList first
        if (isset($this->storage['modules'][$module][self::TABLE_BLACKLIST])) {
            foreach ((array)$this->storage['modules'][$module][self::TABLE_BLACKLIST] as $blackListedModule) {
                if (in_array($blackListedModule, $tableNames)) {
                    unset($tableNames[array_search($blackListedModule, $tableNames)]);
                }
            }
        }

        // Filter Whitelist
        $tables = [];
        if (isset($this->storage['modules'][$module][self::TABLE_WHITELIST])) {
            foreach ((array)$this->storage['modules'][$module][self::TABLE_WHITELIST] as $whiteListedModule) {
                if (in_array($whiteListedModule, $tableNames)) {
                    $tables[] = $whiteListedModule;
                }
            }
        } else {
            $tables = $tableNames;
        }

        return $tables;
    }

}