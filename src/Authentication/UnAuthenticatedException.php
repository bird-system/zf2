<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 22/12/2015
 * Time: 11:43
 */

namespace BS\Authentication;


class UnAuthenticatedException extends \Exception
{
    protected $code = '100111';
}