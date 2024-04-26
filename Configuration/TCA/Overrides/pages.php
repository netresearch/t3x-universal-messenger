<?php

/**
 * This file is part of the package netresearch/nrc-universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\NrcUniversalMessenger\Configuration;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

call_user_func(static function (): void {
    $newsletterDokType = Configuration::getNewsletterPageDokType();

    // Add the new page type to the page type selector
    ExtensionManagementUtility::addTcaSelectItem(
        'pages',
        'doktype',
        [
            'label' => 'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:pages.page_type_newsletter',
            'value' => $newsletterDokType,
            'icon'  => 'universal-messenger-dok-type-newsletter',
            'group' => 'default',
        ],
    );

    ArrayUtility::mergeRecursiveWithOverrule(
        $GLOBALS['TCA']['pages'],
        [
            // Add the icon to the icon class configuration
            'ctrl'  => [
                'typeicon_classes' => [
                    $newsletterDokType => 'universal-messenger-dok-type-newsletter',
                ],
            ],

            // Add all page standard fields and tabs to your new page type
            'types' => [
                $newsletterDokType => [
                    'showitem' => $GLOBALS['TCA']['pages']['types'][PageRepository::DOKTYPE_DEFAULT]['showitem'],
                ],
            ],
        ]
    );
});
