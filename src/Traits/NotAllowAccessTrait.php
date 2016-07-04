<?php
namespace BS\Traits;


use BS\Controller\Exception\MethodNotAllowedException;

trait NotAllowAccessTrait
{

    public function postAction($data)
    {
        throw new MethodNotAllowedException();
    }

    public function deleteAction($id = false)
    {
        throw new MethodNotAllowedException();
    }

    public function indexAction($id = false)
    {
        throw new MethodNotAllowedException();
    }
}