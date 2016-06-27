<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 21/12/2015
 * Time: 14:30
 */

namespace BS\Traits;


use BS\Logger\LoggerHolder;
use Psr\Log\LoggerInterface;

trait LoggerAwareTrait
{

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        LoggerHolder::$logger = $logger;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return LoggerHolder::$logger;
    }

    /**
     * @param       $message
     * @param array $extra
     *
     * @return $this
     */
    public function log($message, $extra = [])
    {
        if (!null == $this->getLogger()) {
            $this->getLogger()->info($message, $extra);
        }

        return $this;
    }
}