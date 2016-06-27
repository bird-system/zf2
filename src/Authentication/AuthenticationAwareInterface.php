<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 19/01/2016
 * Time: 15:09
 */

namespace BS\Authentication;


use Zend\Authentication\AuthenticationService;

interface AuthenticationAwareInterface
{
    /**
     * @return AuthenticationService
     */
    public function getAuthenticationService();

}