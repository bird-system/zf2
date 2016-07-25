<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 23/12/2015
 * Time: 13:31
 */

namespace BS\Controller\Exception;

use BS\Exception\AbstractException;

class MethodNotAllowedException extends AbstractException
{
    protected $code = '100109';

    public function getTranslation()
    {
        return $this->translate('Method not allowed');
    }

}
