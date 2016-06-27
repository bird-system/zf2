<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 23/12/2015
 * Time: 13:31
 */

namespace BS\Controller\Exception;

class MethodNotAllowedException extends AppException
{
    public function __construct($message = 'Method not allowed')
    {
        parent::__construct($message);
    }

}
