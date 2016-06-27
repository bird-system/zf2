<?php


namespace CLI\Model;

class AbstractCodeGenerator
{
    /** @var CodeGeneratorConfiguration */
    protected $config;

    /** @var \Zend\Db\Metadata\Object\TableObject[] */
    protected $tables;

    const NAMESPACE_TEST = 'Tests';
    const NAMESPACE_CONTROLLER = 'Controller';
    const NAMESPACE_MODEL = 'Db\Model';
    const NAMESPACE_TABLEGATEWAY = 'Db\TableGateway';

    const DIRECTORY_SOURCE = 'src';
    const DIRECTORY_TEST = 'tests';


    public function __construct(CodeGeneratorConfiguration $config)
    {
        $this->config = $config;
    }

    /**
     * @return CodeGeneratorConfiguration
     */
    public function getConfig()
    {
        return $this->config;
    }
}