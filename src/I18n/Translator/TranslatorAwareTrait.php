<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 04/01/2016
 * Time: 17:28
 */

namespace BS\I18n\Translator;


use Zend\I18n\Translator\Translator;

trait TranslatorAwareTrait
{

    /**
     * @var Translator
     */
    protected $Translator;

    /**
     * @param $message
     *
     * @return string
     */
    public function t($message)
    {
        return $this->getTranslator()->translate($message);
    }

    /**
     * @return Translator
     */
    public function getTranslator()
    {
        if (!$this->Translator) {
            $this->Translator = $this->serviceLocator->get('translator');
        }

        return $this->Translator;
    }

    /**
     * @param Translator $translator
     *
     * @return $this
     */
    public function setTranslator(Translator $translator)
    {
        $this->Translator = $translator;
    }
}