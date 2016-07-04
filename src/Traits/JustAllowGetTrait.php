<?php
namespace BS\Traits;


use BS\Controller\Exception\MethodNotAllowedException;

trait JustAllowGetTrait
{

    public function postAction($data)
    {
        throw new MethodNotAllowedException();
    }

    public function deleteAction($id = false)
    {
        throw new MethodNotAllowedException();
    }
}