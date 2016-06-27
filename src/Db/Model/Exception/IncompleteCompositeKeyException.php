<?php

namespace BS\Db\Model\Exception;

use BS\Exception;

class IncompleteCompositeKeyException extends Exception
{
    protected $message = 'Composite Key information is not complete';
}
