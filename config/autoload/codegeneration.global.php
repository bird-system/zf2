<?php
use CLI\Model\CodeGeneratorConfiguration;

return [
    'CodeGeneration' => [
        'code_base_path' => realpath(__DIR__ . '/../../module/'),
        'modules'        => [
            'BE' => [
                CodeGeneratorConfiguration::TABLE_WHITELIST => [
                ]
            ]
        ],
        'base_module'    => 'BS'
    ]
];