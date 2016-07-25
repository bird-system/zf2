<?php
namespace BS\Exception;

class CommonWithParamException extends AbstractWithParamException
{
    /**
     * CommonWithParamException constructor.
     *
     * @param array           $message
     * @param array           $msgParams
     * @param string          $code
     * @param \Exception|null $previous
     */
    public function __construct($message, Array $msgParams, $code = '100101', \Exception $previous = null)
    {
        parent::__construct($msgParams, $message, $code, $previous);
    }

    public function getTranslateMessage($message = '')
    {
        return $message;
    }
}