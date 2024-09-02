<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || exit('Access denied.');

// TCA override for sys_template table
call_user_func(static function (): void {
    ExtensionManagementUtility::addStaticFile(
        'universal_messenger',
        'Configuration/TypoScript/FluidContentElements/',
        'Universal Messenger: Fluid Content Elements'
    );

    ExtensionManagementUtility::addStaticFile(
        'universal_messenger',
        'Configuration/TypoScript/ExampleNewsletter/',
        'Universal Messenger: Example Newsletter Template'
    );
});
