<?php
namespace BS\Exception;

use BS\Exception;

abstract class AbstractException extends Exception implements ExceptionInterface
{
    public function __construct($message = '', $code = 0, Exception $previous = null)
    {
        if ($message == '') {
            $message = $this->getTranslateMessage();
        }

        if ($code == 0) {
            $code = $this->code;
        }

        parent::__construct($message, $code, $previous);
    }
}