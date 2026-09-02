<?php

declare(strict_types=1);

$lll = 'LLL:EXT:arp_restaurant/Resources/Private/Language/locallang_db.xlf:';
$tabs = 'LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:';
$general = 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:';

return [
    'ctrl' => [
        'title' => $lll . 'tx_arprestaurant_domain_model_priceoption',
        'label' => 'label',
        'label_alt' => 'amount',
        'label_userFunc' => \Anatolkin\ArpRestaurant\Backend\RecordLabel\RecordLabelProvider::class . '->getPriceOptionTitle',
        'formattedLabel_userFunc' => \Anatolkin\ArpRestaurant\Backend\RecordLabel\RecordLabelProvider::class . '->getPriceOptionTitle',
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
        ],
        'searchFields' => 'label,public_uuid',
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
        'label' => [
            'exclude' => true,
            'label' => $lll . 'tx_arprestaurant_domain_model_priceoption.label',
            'description' => $lll . 'tx_arprestaurant_domain_model_priceoption.label.description',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim',
            ],
        ],
        'amount' => [
            'exclude' => true,
            'label' => $lll . 'tx_arprestaurant_domain_model_priceoption.amount',
            'description' => $lll . 'tx_arprestaurant_domain_model_priceoption.amount.description',
            'l10n_mode' => 'exclude',
            'l10n_display' => 'defaultAsReadonly',
            'config' => [
                'type' => 'number',
                'format' => 'integer',
                'size' => 10,
                'default' => 0,
                'required' => true,
                'range' => [
                    'lower' => 0,
                ],
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
        'placement' => [
            'exclude' => true,
            'label' => $lll . 'tx_arprestaurant_domain_model_priceoption.placement',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_arprestaurant_domain_model_placement',
                'default' => 0,
                'minitems' => 1,
                'maxitems' => 1,
                'required' => true,
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
                    public_uuid, label, amount, placement,
                --div--;' . $tabs . 'language,
                    --palette--;;language,
                --div--;' . $tabs . 'access,
                    --palette--;;hidden,
            ',
        ],
    ],
];
