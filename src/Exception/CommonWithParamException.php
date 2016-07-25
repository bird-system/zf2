<?php
namespace BS\Exception;

class CommonWithParamException extends AbstractWithParamException
{
    /**
     * CommonWithParamException constructor.
     *
     * @param array           $messageParams
     * @param string          $message
     * @param int             $code
     * @param \Exception|null $previous
     */
    public function __construct(Array $messageParams, $message, $code = 100101, \Exception $previous = null)
    {
        parent::__construct($messageParams, $message, $code, $previous);
    }

    public function getTranslation()
    {
        return null;
    }
}