<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'ARP Restaurant',
    'description' => 'Restaurant domain and presentation foundation for TYPO3 CMS 13.4 LTS and 14.3 LTS. This bootstrap does not yet provide menus, ordering, or ARP.top integration.',
    'category' => 'fe',
    'author' => 'Sergey Fedorov',
    'author_email' => 'typo3@anatolkin.com',
    'state' => 'alpha',
    'clearCacheOnLoad' => 1,
    'version' => '0.1.0-dev',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.99.99,14.3.0-14.99.99',
            'php' => '8.2.0-8.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
