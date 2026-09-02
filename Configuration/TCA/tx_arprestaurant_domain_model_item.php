<?php

declare(strict_types=1);

$lll = 'LLL:EXT:arp_restaurant/Resources/Private/Language/locallang_db.xlf:';
$tabs = 'LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:';

return [
    'ctrl' => [
        'title' => $lll . 'tx_arprestaurant_domain_model_item',
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
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'searchFields' => 'title,description,public_uuid',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'columns' => [
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
        'images' => [
            'exclude' => true,
            'label' => $lll . 'tx_arprestaurant_domain_model_item.images',
            'config' => [
                'type' => 'file',
                'allowed' => 'common-image-types',
                'appearance' => [
                    'collapseAll' => true,
                    'useSortable' => true,
                    'showPossibleLocalizationRecords' => true,
                    'showAllLocalizationLink' => true,
                    'showSynchronizationLink' => true,
                ],
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
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
                    title, description, public_uuid, images,
                --div--;' . $tabs . 'language,
                    --palette--;;language,
                --div--;' . $tabs . 'access,
                    --palette--;;hidden,
                    --palette--;;access,
            ',
        ],
    ],
];
