<?php

declare(strict_types=1);

$lll = 'LLL:EXT:arp_restaurant/Resources/Private/Language/locallang_db.xlf:';
$tabs = 'LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:';
$general = 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:';

$inlineAppearance = [
    'collapseAll' => true,
    'expandSingle' => true,
    'useSortable' => true,
    'levelLinksPosition' => 'bottom',
    'showPossibleLocalizationRecords' => true,
    'showAllLocalizationLink' => true,
    'showSynchronizationLink' => true,
    'enabledControls' => [
        'info' => true,
        'new' => true,
        'dragdrop' => true,
        'sort' => true,
        'hide' => true,
        'delete' => true,
        'localize' => true,
    ],
];

return [
    'ctrl' => [
        'title' => $lll . 'tx_arprestaurant_domain_model_category',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'origUid' => 't3_origuid',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'translationSource' => 'l10n_source',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'versioningWS' => true,
        'default_sortby' => 'title',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'title,description,public_uuid',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => $general . 'LGL.enabled',
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
                'items' => [
                    [
                        'label' => '',
                        'invertStateDisplay' => true,
                    ],
                ],
            ],
        ],
        'public_uuid' => [
            'exclude' => true,
            'label' => $lll . 'field.public_uuid',
            'description' => $lll . 'field.public_uuid.description',
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'config' => [
                'type' => 'uuid',
                'version' => 4,
                'required' => true,
            ],
        ],
        'title' => [
            'exclude' => true,
            'label' => $lll . 'field.title',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'max' => 255,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'description' => [
            'exclude' => true,
            'label' => $lll . 'field.description',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 5,
                'eval' => 'trim',
            ],
        ],
        'sorting' => [
            'label' => $lll . 'field.sorting',
            'config' => [
                'type' => 'number',
                'format' => 'integer',
                'default' => 0,
            ],
        ],
        'menu' => [
            'exclude' => true,
            'label' => $lll . 'tx_arprestaurant_domain_model_category.menu',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_arprestaurant_domain_model_menu',
                'foreign_table_where' => 'AND {#tx_arprestaurant_domain_model_menu}.{#sys_language_uid} IN (-1, 0)',
                'default' => 0,
                'minitems' => 1,
                'maxitems' => 1,
                'required' => true,
            ],
        ],
        'placements' => [
            'exclude' => true,
            'label' => $lll . 'tx_arprestaurant_domain_model_category.placements',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_arprestaurant_domain_model_placement',
                'foreign_field' => 'category',
                'foreign_sortby' => 'sorting',
                'appearance' => $inlineAppearance,
                'overrideChildTca' => [
                    'types' => [
                        '0' => [
                            'showitem' => '
                                --div--;' . $tabs . 'general,
                                    public_uuid, item, price_options,
                                --div--;' . $tabs . 'language,
                                    --palette--;;language,
                                --div--;' . $tabs . 'access,
                                    --palette--;;hidden,
                                    --palette--;;access,
                            ',
                        ],
                    ],
                ],
            ],
        ],
    ],
    'palettes' => [
        'language' => [
            'showitem' => 'sys_language_uid, l10n_parent',
        ],
        'hidden' => [
            'showitem' => 'hidden',
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => '
                --div--;' . $tabs . 'general,
                    title, description, public_uuid, menu,
                    placements,
                --div--;' . $tabs . 'language,
                    --palette--;;language,
                --div--;' . $tabs . 'access,
                    --palette--;;hidden,
            ',
        ],
    ],
];
