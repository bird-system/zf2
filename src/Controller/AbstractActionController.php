<?php
namespace BS\Controller;

use BS\Authentication\UnAuthenticatedException;
use BS\I18n\Translator\TranslatorAwareInterface;
use BS\I18n\Translator\TranslatorAwareTrait;
use BS\Traits\LoggerAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Zend\Mvc\Controller\AbstractActionController as Base;
use Zend\Mvc\MvcEvent;

/**
 * Class AbstractActionController
 *
 * @package BS\Controller
 */
abstract class AbstractActionController extends Base implements
    TranslatorAwareInterface, LoggerAwareInterface
{
    use TranslatorAwareTrait, LoggerAwareTrait;

    protected $needAuth = true;

    public function onDispatch(MvcEvent $event)
    {
        try {
            if ($this->needAuth) {
                $this->initAuthenticationService($event);
            }

            return parent::onDispatch($event);
        } catch (UnAuthenticatedException $e) {
            $this->layout($this->getModuleName() . '/login_redirect');
            $event->stopPropagation(true);

            return $this->getResponse();
        }
    }


    public function getParam($param, $default = null)
    {
        if ($this->params()->fromPost($param) !== null) {
            return $this->params()->fromPost($param);
        }

        if ($this->params()->fromQuery($param) !== null) {
            return $this->params()->fromQuery($param);
        }

        return is_null($this->params()->fromRoute($param)) ? $default : $this->params()->fromRoute($param);

    }

    public function getParams()
    {
        return !empty($this->params()->fromPost()) ? $this->params()->fromPost() :
            (!empty($this->params()->fromQuery()) ? $this->params()->fromQuery() :
                $this->params()->fromRoute());
    }

    public function getModuleName()
    {
        $controller = $this->params()->fromRoute('controller');
        $module     = substr($controller, 0, strpos($controller, '\\'));

        return empty($module) ? 'admin' : $module;
    }

    abstract function initAuthenticationService(MvcEvent $event);
}