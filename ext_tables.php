<?php

/**
 * This file is part of the package netresearch/nrc-universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\NrcUniversalMessenger\Configuration;
use TYPO3\CMS\Core\DataHandling\PageDoktypeRegistry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') || exit('Access denied.');

call_user_func(static function (): void {
    $newsletterDokType = Configuration::getNewsletterPageDokType();

    // Add the page type to the system
    $dokTypeRegistry = GeneralUtility::makeInstance(PageDoktypeRegistry::class);
    $dokTypeRegistry->add(
        $newsletterDokType,
        [
            'type'          => 'web',
            'allowedTables' => '*',
        ],
    );

    // We need to add the following user typoscript config to all users, so that the new
    // page type is displayed in the wizard
    ExtensionManagementUtility::addUserTSConfig(
        'options.pageTree.doktypesToShowInNewPageDragArea := addToList(' . $newsletterDokType . ')'
    );
});
