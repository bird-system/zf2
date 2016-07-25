<?php
namespace BS\Exception;

use BS\Exception;

abstract class AbstractWithParamException extends AbstractException
{
    /**
     * @var array
     */
    protected $msgParams;

    public function __construct(Array $msgParams, $message = '', $code = 0, \Exception $previous = null)
    {
        $this->msgParams = $msgParams;

        if ($message == '') {
            $message = $this->getTranslate();
        }

        if ($code == 0) {
            $code = $this->code;
        }

        parent::__construct($message, $code, $previous);
    }

    final public function getMsgParams()
    {
        if (is_array($this->msgParams)) {
            return $this->msgParams;
        } else {
            return [];
        }
    }
}