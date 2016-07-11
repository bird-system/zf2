<?php
namespace BS\Traits;


use Zend\ServiceManager\ServiceManager;

trait ServiceLocatorTrait
{
    /**
     * @var ServiceManager
     */
    protected $serviceLocator = null;

    /**
     * Set service locator
     *
     * @param ServiceManager $serviceLocator
     *
     * @return mixed
     */
    public function setServiceLocator(ServiceManager $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;

        return $this;
    }

    /**
     * Get service locator
     *
     * @return ServiceManager
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }
}