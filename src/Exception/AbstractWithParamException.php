<?php
namespace BS\Exception;

abstract class AbstractWithParamException extends AbstractException
{
    /**
     * @var array
     */
    protected $messageParams;

    public function __construct(Array $messageParams, $message = '', $code = 0, \Exception $previous = null)
    {
        $this->messageParams = $messageParams;

        parent::__construct($message, $code, $previous);
    }

    final public function getMessageParams()
    {
        return is_array($this->messageParams) ? $this->messageParams : [];
    }
}