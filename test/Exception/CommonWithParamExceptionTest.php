<?php


namespace BS\Test\Exception;


use BS\Exception\CommonWithParamException;
use PHPUnit\Framework\TestCase;

class CommonWithParamExceptionTest extends TestCase
{
    public function testThrow()
    {
        try {
            throw new CommonWithParamException(['conent' => 'ok'], 'Test');
        } catch (CommonWithParamException $e) {
            $this->assertEquals('Test', $e->getMessage());
            $this->assertEquals(['conent' => 'ok'], $e->getMessageParams());
        }
    }
}
