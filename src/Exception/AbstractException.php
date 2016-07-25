<?php
namespace BS\Exception;


abstract class AbstractException extends \Exception
{
    public function __construct($message = '', $code = 0, \Exception $previous = null)
    {
        parent::__construct(
            $message == '' ? $this->getTranslation() : $message,
            $code == 0 ? $this->code : $code,
            $previous
        );
    }

    abstract function getTranslation();

    /**
     * This function is for PoEdit to recongize the 'translate' keyword
     *
     * @param string $message
     *
     * @return string
     */
    protected function translate($message = '')
    {
        return $message;
    }
}