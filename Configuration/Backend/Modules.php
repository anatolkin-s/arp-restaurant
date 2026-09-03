<?php

declare(strict_types=1);

use Anatolkin\ArpRestaurant\Backend\Controller\RestaurantEditorController;

$lll = 'LLL:EXT:arp_restaurant/Resources/Private/Language/locallang_mod_editor.xlf:';

return [
    'web_arp_restaurant_editor' => [
        'parent' => 'web',
        'position' => ['after' => 'web_list'],
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/web/arp-restaurant-editor',
        'iconIdentifier' => 'module-arp-restaurant-editor',
        'labels' => [
            'title' => $lll . 'mlang_tabs_tab',
            'description' => $lll . 'mlang_labels_tabdescr',
            'shortDescription' => $lll . 'mlang_labels_tablabel',
        ],
        'routes' => [
            '_default' => [
                'target' => RestaurantEditorController::class . '::handleRequest',
            ],
        ],
    ],
];
