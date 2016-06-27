<?php

namespace CLI;

use CLI\Controller\AbstractConsoleActionController;
use Zend\Code\Scanner\ClassScanner;
use Zend\Code\Scanner\DirectoryScanner;
use Zend\Console\Adapter\AdapterInterface as ConsoleAdapterInterface;
use Zend\EventManager\EventInterface;
use Zend\ModuleManager\Feature\BootstrapListenerInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ConsoleBannerProviderInterface;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\Mvc\Controller\ControllerManager;
use Zend\ServiceManager\ServiceLocatorInterface;

class Module implements
    ConsoleUsageProviderInterface,
    ConfigProviderInterface,
    ConsoleBannerProviderInterface,
    BootstrapListenerInterface
{
    const NAME = 'BirdSystem Command Line Interface';

    /**
     * @var ServiceLocatorInterface
     */
    protected $sm;

    public function onBootstrap(EventInterface $e)
    {
        $this->sm = $e->getApplication()->getServiceManager();
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }


    public function getConsoleBanner(ConsoleAdapterInterface $console)
    {
        return self::NAME;
    }

    /**
     * @param ConsoleAdapterInterface $console
     *
     * @return array|void
     */
    public function getConsoleUsage(ConsoleAdapterInterface $console)
    {
        $config = $this->sm->get('config');
        if (!empty($config['CodeGenerator']) && !empty($config['CodeGenerator']['disable_usage'])) {
            return []; // usage information has been disabled
        }

        /** @var ControllerManager $ControllerManager */
        $ControllerManager = $this->sm->get('controllerloader');
        $result            = [];
        $DirectoryScanner  = new DirectoryScanner(__DIR__ . '/src/Controller/');
        foreach ($DirectoryScanner->getClasses(true) as $classScanner) {
            /** @var ClassScanner $classScanner */
            $className = $classScanner->getName();
            $class     = new \ReflectionClass($className);
            if ($class->isAbstract()) {
                continue;
            }
            /** @var AbstractConsoleActionController $controller */
            $controller = $ControllerManager->get($className);
            $result[]   = $controller->getBanner();
            $helps      = $controller->getHelp();
            $result     = array_merge($result, $helps);
        }

        return $result;
    }
}
