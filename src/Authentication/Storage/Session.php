<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 17/12/2015
 * Time: 23:29
 */

namespace BS\Authentication\Storage;

use Zend\Authentication\Storage\Session as Base;

class Session extends Base
{
    public function rememberMe()
    {
        $this->session->getManager()->rememberMe();
    }

    public function forgetMe()
    {
        $this->session->getManager()->forgetMe();
    }

    public function setExpirationSeconds($tll)
    {
        return $this->session->setExpirationSeconds($tll);
    }

}