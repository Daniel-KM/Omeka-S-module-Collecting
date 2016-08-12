<?php
return [
    'view_manager' => [
        'template_path_stack' => [
            OMEKA_PATH . '/modules/Collecting/view',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'collecting' => 'Collecting\View\Helper\Collecting',
            'collectingPrepareForm' => 'Collecting\View\Helper\CollectingPrepareForm',
            'formPromptHtml' => 'Collecting\Form\View\Helper\FormPromptHtml',
        ],
    ],
    'controllers' => [
        'invokables' => [
            'Collecting\Controller\SiteAdmin\Form' => 'Collecting\Controller\SiteAdmin\FormController',
            'Collecting\Controller\SiteAdmin\Item' => 'Collecting\Controller\SiteAdmin\ItemController',
            'Collecting\Controller\SiteAdmin\User' => 'Collecting\Controller\SiteAdmin\UserController',
        ],
        'factories' => [
            'Collecting\Controller\Site\Index' => 'Collecting\Service\Controller\Site\IndexControllerFactory',
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'collectingCurrentForm' => 'Collecting\Mvc\Controller\Plugin\CollectingCurrentForm',
        ],
    ],
    'block_layouts' => [
        'invokables' => [
            'collecting' => 'Collecting\Site\BlockLayout\Collecting',
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            OMEKA_PATH . '/modules/Collecting/src/Entity',
        ],
        'proxy_paths' => [
            OMEKA_PATH . '/modules/Collecting/data/doctrine-proxies',
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'collecting_forms' => 'Collecting\Api\Adapter\CollectingFormAdapter',
            'collecting_items' => 'Collecting\Api\Adapter\CollectingItemAdapter',
            'collecting_users' => 'Collecting\Api\Adapter\CollectingUserAdapter',
        ],
    ],
    'navigation' => [
        'site' => [
            [
                'label' => 'Collecting', // @translate
                'route' => 'admin/site/slug/collecting',
                'action' => 'index',
                'useRouteMatch' => true,
                'pages' => [
                    [
                        'route' => 'admin/site/slug/collecting/id',
                        'visible' => false,
                    ],
                    [
                        'route' => 'admin/site/slug/collecting/default',
                        'visible' => false,
                    ],
                    [
                        'route' => 'admin/site/slug/collecting/item',
                        'visible' => false,
                    ],
                    [
                        'route' => 'admin/site/slug/collecting/item/id',
                        'visible' => false,
                    ],
                ],
            ],
        ],
        'Collecting' => [
            [
                'label' => 'Form Information', // @translate
                'route' => 'admin/site/slug/collecting/id',
                'action' => 'show',
                'useRouteMatch' => true,
            ],
            [
                'label' => 'Collected Items', // @translate
                'route' => 'admin/site/slug/collecting/item',
                'action' => 'index',
                'useRouteMatch' => true,
                'pages' => [
                    [
                        'route' => 'admin/site/slug/collecting/item/id',
                        'visible' => false,
                    ],
                ],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'collecting' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/collecting/:form-id/:action',
                            'defaults' => [
                                '__NAMESPACE__' => 'Collecting\Controller\Site',
                            ],
                            'constraints' => [
                                'form-id' => '\d+',
                            ],
                        ],
                    ],
                ],
            ],
            'admin' => [
                'child_routes' => [
                    'site' => [
                        'child_routes' => [
                            'slug' => [
                                'child_routes' => [
                                    'collecting' => [
                                        'type' => 'Literal',
                                        'options' => [
                                            'route' => '/collecting',
                                            'defaults' => [
                                                '__NAMESPACE__' => 'Collecting\Controller\SiteAdmin',
                                                'controller' => 'Form',
                                                'action' => 'index',
                                            ],
                                        ],
                                        'may_terminate' => true,
                                        'child_routes' => [
                                            'id' => [
                                                'type' => 'Segment',
                                                'options' => [
                                                    'route' => '/:form-id[/:action]',
                                                    'constraints' => [
                                                        'form-id' => '\d+',
                                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                                    ],
                                                    'defaults' => [
                                                        'action' => 'show',
                                                    ],
                                                ],
                                            ],
                                            'default' => [
                                                'type' => 'Segment',
                                                'options' => [
                                                    'route' => '/:action',
                                                    'constraints' => [
                                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                                    ],
                                                ],
                                            ],
                                            'item' => [
                                                'type' => 'Segment',
                                                'options' => [
                                                    'route' => '/:form-id/item',
                                                    'constraints' => [
                                                        'form-id' => '\d+',
                                                    ],
                                                    'defaults' => [
                                                        'controller' => 'Item',
                                                        'action' => 'index',
                                                    ],
                                                ],
                                                'may_terminate' => true,
                                                'child_routes' => [
                                                    'id' => [
                                                        'type' => 'Segment',
                                                        'options' => [
                                                            'route' => '/:item-id[/:action]',
                                                            'constraints' => [
                                                                'item-id' => '\d+',
                                                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                                            ],
                                                            'defaults' => [
                                                                'action' => 'show',
                                                            ],
                                                        ],
                                                    ],
                                                    'default' => [
                                                        'type' => 'Segment',
                                                        'options' => [
                                                            'route' => '/:action',
                                                            'constraints' => [
                                                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ]
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
