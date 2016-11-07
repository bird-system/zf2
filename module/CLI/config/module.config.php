<?php
namespace CLI;

use Zend\ServiceManager\Factory\InvokableFactory;

return [
    'CLI'         => [
        'disable_usage' => false,    // set to true to disable showing available ZFTool commands in Console.
    ],
    'controllers' => [
        'factories' => [
            'CLI\Controller\CacheController'         => InvokableFactory::class,
            'CLI\Controller\CodeGeneratorController' => InvokableFactory::class,
            'CLI\Controller\PHPUnitController'       => InvokableFactory::class,
            'CLI\Controller\DockerComposeController' => InvokableFactory::class,
        ],
    ],
    'console'     => [
        'router' => [
            'routes' => [
                'code-default'            => [
                    'options' => [
                        'route'    => 'code [<action>]',
                        'defaults' => [
                            'controller' => 'CLI\Controller\CodeGeneratorController',
                            'action'     => 'help',
                        ],
                    ],
                ],
                'code-generate'           => [
                    'options' => [
                        'route'    => 'code generate [--all|--source|--test]:type ' .
                                      '[--modules=] [--tables=] [--output] [--force-overwrite|-f]',
                        'defaults' => [
                            'controller' => 'CLI\Controller\CodeGeneratorController',
                            'action'     => 'generate',
                        ],
                    ],
                ],
                'code-generate-source'    => [
                    'options' => [
                        'route'    => 'code generate-source [--modules=] [--tables=] [--output] [--force-overwrite|-f]',
                        'defaults' => [
                            'controller' => 'CLI\Controller\CodeGeneratorController',
                            'action'     => 'generate-source',
                        ],
                    ],
                ],
                'code-generate-test'      => [
                    'options' => [
                        'route'    => 'code generate-test [--modules=] [--tables=] [--output] [--force-overwrite|-f]',
                        'defaults' => [
                            'controller' => 'CLI\Controller\CodeGeneratorController',
                            'action'     => 'generate-test',
                        ],
                    ],
                ],
                'cache-clear'             => [
                    'options' => [
                        'route'    => 'cache clear [all|metadata|default]:mode',
                        'defaults' => [
                            'controller' => 'CLI\Controller\CacheController',
                            'action'     => 'clear',
                        ],
                    ],
                ],
                'phpunit-show-testsuites' => [
                    'options' => [
                        'route'    => 'phpunit show-testsuites <file>',
                        'defaults' => [
                            'controller' => 'CLI\Controller\PHPUnitController',
                            'action'     => 'show-testsuites',
                        ],
                    ],
                ],
                'docker-compose-generate-name' => [
                    'options' => [
                        'route'    => 'docker-compose generate-name <test-suite>',
                        'defaults' => [
                            'controller' => 'CLI\Controller\DockerComposeController',
                            'action'     => 'generate-name',
                        ],
                    ],
                ],
                'docker-compose-generate-content' => [
                    'options' => [
                        'route'    => 'docker-compose generate-content <test-suite> <file-seed>  [v2]:version',
                        'defaults' => [
                            'controller' => 'CLI\Controller\DockerComposeController',
                            'action'     => 'generate-content',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
