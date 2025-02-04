<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\UniversalMessenger\Configuration;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

call_user_func(static function (): void {
    // Register our example backend layout
    ExtensionManagementUtility::registerPageTSConfigFile(
        'universal_messenger',
        'Configuration/TsConfig/Page/Mod/WebLayout/example_newsletter.tsconfig',
        'Universal Messenger: Example Newsletter Backend Layout'
    );

    $configuration         = GeneralUtility::makeInstance(Configuration::class);
    $newsletterPageDokType = $configuration->getNewsletterPageDokType();

    // Add the new page type to the page type selector
    ExtensionManagementUtility::addTcaSelectItem(
        'pages',
        'doktype',
        [
            'label' => 'LLL:EXT:universal_messenger/Resources/Private/Language/locallang.xlf:pages.page_type_newsletter',
            'value' => $newsletterPageDokType,
            'icon'  => 'universal-messenger-dok-type-newsletter',
            'group' => 'default',
        ],
    );

    ArrayUtility::mergeRecursiveWithOverrule(
        $GLOBALS['TCA']['pages'],
        [
            // Add the icon to the icon class configuration
            'ctrl' => [
                'typeicon_classes' => [
                    $newsletterPageDokType                 => 'universal-messenger-dok-type-newsletter',
                    $newsletterPageDokType . '-hideinmenu' => 'universal-messenger-dok-type-newsletter',
                ],
            ],

            // Add all page standard fields and tabs to your new page type
            'types' => [
                $newsletterPageDokType => [
                    'showitem' => $GLOBALS['TCA']['pages']['types'][PageRepository::DOKTYPE_DEFAULT]['showitem'],
                ],
            ],
        ]
    );

    $columns = [
        'universal_messenger_channel' => [
            'exclude'     => true,
            'label'       => 'LLL:EXT:universal_messenger/Resources/Private/Language/locallang.xlf:pages.universal_messenger_channel',
            'description' => 'LLL:EXT:universal_messenger/Resources/Private/Language/locallang.xlf:pages.universal_messenger_channel.description',
            'displayCond' => 'FIELD:doktype:=:' . $newsletterPageDokType,
            'config'      => [
                'type'          => 'select',
                'renderType'    => 'selectSingle',
                'foreign_table' => 'tx_universalmessenger_domain_model_newsletterchannel',
                'items'         => [
                    [
                        'label' => 'LLL:EXT:universal_messenger/Resources/Private/Language/locallang.xlf:pages.tx_universalmessenger_domain_model_newsletterchannel.0',
                        'value' => '0',
                    ],
                ],
            ],
        ],
    ];

    ExtensionManagementUtility::addTCAcolumns(
        'pages',
        $columns
    );

    ExtensionManagementUtility::addToAllTCAtypes(
        'pages',
        '--linebreak--, universal_messenger_channel',
        '',
        'after:doktype'
    );
});
