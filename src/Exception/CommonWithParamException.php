<?php
namespace BS\Exception;

class CommonWithParamException extends AbstractWithParamException
{
    /**
     * CommonWithParamException constructor.
     *
     * @param string          $message
     * @param array           $messageParams
     * @param int             $code
     * @param \Exception|null $previous
     */
    public function __construct($message, Array $messageParams, $code = 100101, \Exception $previous = null)
    {
        parent::__construct($messageParams, $message, $code, $previous);
    }

    public function getTranslation()
    {
        return null;
    }
}