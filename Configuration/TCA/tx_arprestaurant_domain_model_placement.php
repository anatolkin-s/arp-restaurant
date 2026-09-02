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
        'title' => $lll . 'tx_arprestaurant_domain_model_placement',
        'label' => 'item',
        'label_alt' => 'public_uuid',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'origUid' => 't3_origuid',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'translationSource' => 'l10n_source',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'versioningWS' => true,
        'hideTable' => true,
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'searchFields' => 'public_uuid',
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
        'starttime' => [
            'exclude' => true,
            'label' => $general . 'LGL.starttime',
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'default' => 0,
            ],
        ],
        'endtime' => [
            'exclude' => true,
            'label' => $general . 'LGL.endtime',
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'default' => 0,
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
        'sorting' => [
            'label' => $lll . 'field.sorting',
            'config' => [
                'type' => 'number',
                'format' => 'integer',
                'default' => 0,
            ],
        ],
        'category' => [
            'exclude' => true,
            'label' => $lll . 'tx_arprestaurant_domain_model_placement.category',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_arprestaurant_domain_model_category',
                'foreign_table_where' => 'AND {#tx_arprestaurant_domain_model_category}.{#sys_language_uid} IN (-1, 0)',
                'default' => 0,
                'minitems' => 1,
                'maxitems' => 1,
                'required' => true,
            ],
        ],
        'item' => [
            'exclude' => true,
            'label' => $lll . 'tx_arprestaurant_domain_model_placement.item',
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_arprestaurant_domain_model_item',
                'foreign_table_where' => 'AND {#tx_arprestaurant_domain_model_item}.{#sys_language_uid} IN (-1, 0) ORDER BY title',
                'default' => 0,
                'minitems' => 1,
                'maxitems' => 1,
                'required' => true,
            ],
        ],
        'price_options' => [
            'exclude' => true,
            'label' => $lll . 'tx_arprestaurant_domain_model_placement.price_options',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_arprestaurant_domain_model_priceoption',
                'foreign_field' => 'placement',
                'foreign_sortby' => 'sorting',
                'appearance' => $inlineAppearance,
                'overrideChildTca' => [
                    'types' => [
                        '0' => [
                            'showitem' => '
                                --div--;' . $tabs . 'general,
                                    public_uuid, label, amount,
                                --div--;' . $tabs . 'language,
                                    --palette--;;language,
                                --div--;' . $tabs . 'access,
                                    --palette--;;hidden,
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
        'access' => [
            'showitem' => 'starttime, endtime',
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => '
                --div--;' . $tabs . 'general,
                    public_uuid, category, item, price_options,
                --div--;' . $tabs . 'language,
                    --palette--;;language,
                --div--;' . $tabs . 'access,
                    --palette--;;hidden,
                    --palette--;;access,
            ',
        ],
    ],
];
