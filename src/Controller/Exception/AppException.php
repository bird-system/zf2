<?php

namespace BS\Controller\Exception;

use BS\Exception;

class AppException extends Exception
{
    public function __construct($message = '')
    {
        parent::__construct($message);
    }
}
