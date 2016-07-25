<?php
namespace BS\Exception;

interface ExceptionInterface
{
    public function getTranslateMessage($message = '');
}