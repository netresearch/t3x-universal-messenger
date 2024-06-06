<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\UniversalMessenger\Configuration;
use TYPO3\CMS\Core\DataHandling\PageDoktypeRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') || exit('Access denied.');

call_user_func(static function (): void {
    // Add the page type to the system
    $dokTypeRegistry = GeneralUtility::makeInstance(PageDoktypeRegistry::class);
    $dokTypeRegistry->add(
        Configuration::getNewsletterPageDokType(),
        [
            'type'          => 'web',
            'allowedTables' => '*',
        ],
    );
});
