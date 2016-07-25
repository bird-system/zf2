<?php
namespace BS\Exception;

use BS\Exception;

abstract class AbstractException extends Exception
{
    public function __construct($message = '', $code = 0, Exception $previous = null)
    {
        if ($message == '') {
            $message = $this->getTranslate();
        }

        if ($code == 0) {
            $code = $this->code;
        }

        parent::__construct($message, $code, $previous);
    }

    abstract function getTranslate();

    final public function translate($message = '')
    {
        return $message;
    }
}