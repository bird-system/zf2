<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 20/01/2016
 * Time: 15:08
 */

namespace CLI\Controller;


use CLI\Exception;
use CLI\Model\AbstractCodeGenerator;
use CLI\Model\CodeGenerator\AbstractFileGenerator;
use CLI\Model\CodeGeneratorConfiguration;
use CLI\Model\SourceCodeGenerator;
use CLI\Model\TestCodeGenerator;
use Zend\Code\Scanner\DirectoryScanner;
use Zend\Console\ColorInterface;
use Zend\Db\Metadata\Metadata;
use Zend\Db\Metadata\Object\TableObject;

class CodeGeneratorController extends AbstractConsoleActionController
{
    protected $banner = 'Code Generator';

    protected $help = [
        '__SCRIPT__ code show-config'                      => 'Show Code Generation configuration.',
        '__SCRIPT__ code show-db'                          => 'Show DB Information to be used in generation.',
        '__SCRIPT__ code generate [--all|--source|--test]' => 'Generate source code or test code or both',
        ['--modules', 'List of modules to generate, overrides configuation'],
        ['--tables', 'Whitelist of tables for code generation, overrides configuation.'],
        ['--output', 'Output the generated code only instead of writing to disk.'],
        ['--force-overwrite | -f', 'Ignore default ovewrite settings, force overwrite everything.'],
        '__SCRIPT__ code generate-source'                  => 'Generate source code only, ' .
                                                              'all other controls in \'generate\' works',
        '__SCRIPT__ code generate-test'                    => 'Generate test code only, ' .
                                                              'all other controls in \'generate\' works',
    ];

    /** @var  CodeGeneratorConfiguration */
    protected $config;


    /** @var  TableObject[] */
    protected $tables;

    /** @var  [] */
    protected $tableNames;


    public function generateAction()
    {
        $type = 'all';
        if ($this->getRequest()->getParam('type')) {
            if ($this->getRequest()->getParam('source')) {
                $type = 'source';
            } else {
                $type = 'test';
            }
        }

        if (in_array($type, ['all', 'source'])) {
            $this->generateSourceAction();
        }
        if (in_array($type, ['all', 'test'])) {
            $this->generateTestAction();
        }
    }

    public function generateSourceAction()
    {
        $this->console->writeLine('Generating Source Code...');
        $CodeGenerator = new SourceCodeGenerator($this->getConfig(), $this->serviceLocator->get('db'));
        $this->generateCode($CodeGenerator);
        $this->console->writeLine('...Done');
    }

    public function generateTestAction()
    {
        $this->console->writeLine('Generating Test Code...');
        $CodeGenerator = new TestCodeGenerator($this->getConfig(), $this->serviceLocator->get('db'));
        $this->generateCode($CodeGenerator);
        $this->console->writeLine('...Done');

    }

    protected function generateCode(AbstractCodeGenerator $CodeGenerator)
    {
        $CodeGeneratorReflection        = new \ReflectionObject($CodeGenerator);
        $CodeGeneratorReflectionMethods = $CodeGeneratorReflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        $outputMode                     = $this->getRequest()->getParam('output', false);
        $forceOverwrite                 = $this->getRequest()->getParam('force-overwrite')
                                          || $this->getRequest()->getParam('f');

        $baseModuleGenerated    = false;
        $baseModuleMethodNumber = 0;
        foreach ($this->getConfig()->getModules() as $module => $moduleConfig) {
            $this->console->writeLine(sprintf('[%s] module:', $module), ColorInterface::GREEN);
            $module        = ucfirst($module);
            if($this->getRequest()->getParam('tables')){
                $tableNames = explode(',',$this->getRequest()->getParam('tables'));
            }else {
                $tableNames = $this->getConfig()->getModuleTables($module, $this->getTableNames());
            }
            $tables        = $this->getTables($tableNames);
            $numberOfFiles = count($tables) * (count($CodeGeneratorReflectionMethods) - 1) - $baseModuleMethodNumber;
            $counter       = 1;
            foreach ($tables as $table) {
                foreach ($CodeGeneratorReflectionMethods as $method) {
                    if ($method->isConstructor()) {
                        continue;
                    }
                    if (strpos($method->getName(), 'BaseModule')) {
                        $baseModuleMethodNumber++;
                        if ($baseModuleGenerated) {
                            continue;
                        }
                    }
                    /** @var AbstractFileGenerator $FileGenerator */
                    switch ($method->getNumberOfParameters()) {
                        case 1:
                            $FileGenerator = $method->invokeArgs($CodeGenerator, [$table]);
                            break;
                        case 2:
                            $FileGenerator = $method->invokeArgs($CodeGenerator, [$table, $module]);
                            break;
                        default:
                            continue;
                    }
                    if (!$outputMode) {
                        if($numberOfFiles > 0) {
                            printf("\r%6.2f%% (%s/%s)", ($counter / $numberOfFiles) * 100, $counter++, $numberOfFiles);
                        }
                    } else {
                        $this->console->writeLine(
                            $this->console->colorize($FileGenerator->getFilePath(), ColorInterface::LIGHT_BLUE)
                            . "\t" .
                            $this->console->colorize('Overwrite ' . $FileGenerator->getOverwriteMode(),
                                $FileGenerator::OVERWRITE_MODE_ON ==
                                $FileGenerator->getOverwriteMode() ?
                                    ColorInterface::RED : ColorInterface::GREEN)
                        );
                    }
                    $FileGenerator->write($outputMode || $forceOverwrite);
                }
            }
            $this->console->clearLine();
            // Code in BaseModule only need to be generated only once
            $baseModuleGenerated = true;
        }
    }


    /**
     * @return CodeGeneratorConfiguration
     * @throws Exception
     */
    protected function getConfig()
    {
        if (!$this->config) {
            $config = $this->serviceLocator->get('config');
            if (!isset($config['CodeGeneration'])) {
                throw new Exception('No configuration');
            }
            $this->config = new CodeGeneratorConfiguration($config['CodeGeneration']);
            $paramModules = $this->getRequest()->getParam('modules')
                ? explode(',', $this->getRequest()->getParam('modules')) : [];
            $paramTables  = $this->getRequest()->getParam('tables')
                ? explode(',', $this->getRequest()->getParam('tables')) : null;

            foreach ($this->config->getModules() as $key => $module) {
                if (is_string($module)) {
                    $this->config->removeModule($key);
                    $key = $module;
                    $this->config->addModule($module, $paramTables);
                }
                if (count($paramModules) > 0 && !in_array($key, $paramModules)) {
                    $this->config->removeModule($key);
                }
            }
            foreach ($paramModules as $module) {
                $this->config->addModule($module, $paramTables);
            }
        }

        return $this->config;
    }

    public function showConfigAction()
    {
        $config = $this->getConfig();

        $this->console->writeLine(
            $this->console->colorize('Code base path: ', ColorInterface::GREEN) .
            $this->console->colorize($config->getCodeBasePath(), ColorInterface::NORMAL)
        );
        $this->console->writeLine(
            $this->console->colorize('Base Module: ', ColorInterface::GREEN) .
            $this->console->colorize($config->getBaseModule(), ColorInterface::NORMAL)
        );
        $this->console->writeLine(
            $this->console->colorize('Modules: ', ColorInterface::GREEN) .
            $this->console->colorize(implode(' , ', array_keys($config->getModules())), ColorInterface::NORMAL)
        );

        $BaseModuleControllerDirectoryScanner =
            new DirectoryScanner($config->getBaseModuleSourcePath() . DIRECTORY_SEPARATOR
                                 . AbstractCodeGenerator::NAMESPACE_CONTROLLER);
        $this->console->writeLine(
            $this->console->colorize('Number of base module controllers: ', ColorInterface::GREEN) .
            $this->console->colorize(count($BaseModuleControllerDirectoryScanner->getFiles()), ColorInterface::NORMAL)
        );
    }

    public function showDbAction()
    {
        $tables = $this->getTableNames();
        foreach ($tables as $table) {
            $this->console->writeLine($table);
        }
    }

    /**
     * @param array $tableNames
     *
     * @return TableObject[]
     */
    protected function getTables($tableNames = [])
    {
        $result = [];
        if (!$this->tables) {
            $this->console->write('Getting database table info....');
            $dbAdapter = $this->serviceLocator->get('db');
            $metadata  = new Metadata($dbAdapter);
//            $dbAdapter->query('set global innodb_stats_on_metadata=0;');
            $this->tables = $metadata->getTables();
//            $dbAdapter->query('set global innodb_stats_on_metadata=1;');
            $this->console->write('Done!');
            $this->console->clearLine();
        }


        if (count($tableNames) > 0) {
            foreach ($this->tables as $table) {
                if (in_array($table->getName(), $tableNames)) {
                    $result[$table->getName()] = $table;
                }
            }
        } else {
            $result = $this->tables;
        }

        return $result;
    }

    protected function getTableNames()
    {

        if (!$this->tableNames) {
            $dbAdapter = $this->serviceLocator->get('db');
            $metadata  = new Metadata($dbAdapter);
//            $dbAdapter->query('set global innodb_stats_on_metadata=0;');
            $this->tableNames = $metadata->getTableNames();
//            $dbAdapter->query('set global innodb_stats_on_metadata=1;');
        }

        return $this->tableNames;
    }

}