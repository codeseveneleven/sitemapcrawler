<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 project.
 * (c) 2025 12bis3
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 * The TYPO3 project - inspiring people to share!
 *
 * @copyright 2025 12bis3
 */

return [
    'ctrl' => [
        'title' => 'LLL:EXT:sitemapcrawler/Resources/Private/Language/locallang.xlf:tx_sitemapcrawler_domain_model_log',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:sitemapcrawler/Resources/Public/Icons/Extension.svg',
        'security' => [
            'ignoreWebMountRestriction' => true,
            'ignorePageTypeRestriction' => true,
            'ignoreRootLevelRestriction' => true,
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => '
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,title,lastrun,mode,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                    --palette--;;hidden
            ',
        ],
    ],
    'palettes' => [
        'hidden' => [
            'showitem' => 'hidden',
        ],
    ],
    'columns' => [
        'hidden' => [
            'label' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:field.default.hidden',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'title' => [
            'label' => 'LLL:EXT:sitemapcrawler/Resources/Private/Language/locallang.xlf:tx_sitemapcrawler_domain_model_log.title',
            'config' => [
                'type' => 'input',
            ],
        ],
        'lastrun' => [
            'label' => 'LLL:EXT:sitemapcrawler/Resources/Private/Language/locallang.xlf:tx_sitemapcrawler_domain_model_log.lastrun',
            'config' => [
                'type' => 'datetime',
            ],
        ],
        'mode' => [
            'label' => 'LLL:EXT:sitemapcrawler/Resources/Private/Language/locallang.xlf:tx_sitemapcrawler_domain_model_log.mode',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:sitemapcrawler/Resources/Private/Language/locallang.xlf:tx_sitemapcrawler_domain_model_log.mode.0',
                        'value' => 0,
                    ],
                    [
                        'label' => 'LLL:EXT:sitemapcrawler/Resources/Private/Language/locallang.xlf:tx_sitemapcrawler_domain_model_log.mode.1',
                        'value' => 1,
                    ],
                ],
                'default' => 0,
            ],
        ],
        'count' => [
            'label' => 'LLL:EXT:sitemapcrawler/Resources/Private/Language/locallang.xlf:tx_sitemapcrawler_domain_model_log.count',
            'config' => [
                'type' => 'input',
            ],
        ],
    ],
];
