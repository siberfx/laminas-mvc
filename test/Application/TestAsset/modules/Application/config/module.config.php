<?php

/**
 * @see       https://github.com/laminas/laminas-mvc for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc/blob/master/LICENSE.md New BSD License
 */

namespace Application;

use Laminas\Http\PhpEnvironment\Request as HttpRequest;
use Laminas\Http\PhpEnvironment\Response as HttpResponse;
use Laminas\Mvc\Service\HttpRouterFactory;
use Laminas\Mvc\Service\HttpViewManagerFactory;

return [
    'controllers' => [
        'factories' => [
            'path' => function () {
                return new Controller\PathController();
            },
        ],
    ],
    'router' => [
        'routes' => [
            'path' => [
                'type' => 'literal',
                'options' => [
                    'route' => '/path',
                    'defaults' => [
                        'controller' => 'path',
                    ],
                ],
            ],
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Request' => function () {
                return new HttpRequest();
            },
            'Response' => function () {
                return new HttpResponse();
            },
            'Router'      => HttpRouterFactory::class,
            'ViewManager' => HttpViewManagerFactory::class,
        ],
    ],
    'view_manager' => array(
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => array(
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ),
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
];
