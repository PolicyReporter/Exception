<?php

namespace Exception;

use Authentication\Utility\Legacy\UserLevel;
use Exception\Controller\ExceptionController;
use Exception\Service;

return [
    'service_manager' => [
        'invokables' => [
            Service\Exception::class => Service\Exception::class,
        ],
    ],

    'controllers' => [
        'invokables' => [
            ExceptionController::class => ExceptionController::class,
        ],
    ],

    'controller_plugins' => [],
    'router' => [
        'routes' => [
            'errors' => [
                'type' => 'segment',
                'may_terminate' => false,
                'options' => [
                    'route' => '/errors/:code',
                    'defaults' => [
                        'controller' => ExceptionController::class,
                        'action' => 'throw-error',
                        'authentication' => [
                            'http' => false,
                            'level' => UserLevel::ADMIN,
                            'flag' => null,
                        ],
                    ],
                    'constraints' => [
                        'code' => '[0-9]+',
                    ],
                ],
            ],
        ],
    ],
    'view_manager' => [
        'template_map' => [
            'error/404' => __DIR__ . '/../view/404.phtml',
            'error/index' => __DIR__ . '/../view/index.phtml',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
        'strategies' => [],
    ],
];
