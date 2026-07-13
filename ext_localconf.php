<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\UniversalMessenger\Controller\NewsletterPreviewController;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || exit('Access denied.');

call_user_func(static function (): void {
    // Register the extension's default TypoScript for classic (sys_template-based) sites
    // via the global default TypoScript setup. Site-Set-based v14 sites load the same
    // TypoScript through the "netresearch/universal-messenger" Site Set instead (see
    // Configuration/Sets/UniversalMessenger), so includeInSiteSets is disabled here to
    // avoid force-injecting it into every site-set site and loading it twice.
    ExtensionManagementUtility::addTypoScript(
        'universal_messenger',
        'setup',
        '@import "EXT:universal_messenger/Configuration/TypoScript/Default/setup.typoscript"',
        0,
        false,
    );

    // The newsletter page type is registered in the page-type selector via
    // Configuration/TCA/Overrides/pages.php (addTcaSelectItem). Since TYPO3 v14.2 the page
    // tree "new page" drag area determines the selectable doktypes automatically from the
    // backend user's group permissions (pagetypes_select) / admin status, so no explicit
    // user TSconfig registration is needed anymore.
    //
    // The former "options.pageTree.doktypesToShowInNewPageDragArea" user TSconfig option was
    // deprecated in v14.2 and will be removed in v15.0 (see TYPO3 changelog #109196); setting
    // it now only triggers a deprecation log while yielding the same, permission-gated result.
    ExtensionUtility::configurePlugin(
        'UniversalMessenger',
        'NewsletterPreview',
        [
            NewsletterPreviewController::class => 'preview',
        ],
        [
            NewsletterPreviewController::class => 'preview',
        ],
    );

    // Ignore the following parameters in cHash calculation
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'tx_universalmessenger_newsletterpreview[pageId]';
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'type';
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'preview';

    // Add our custom style sheet
    $GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['universal_messenger']
        = 'EXT:universal_messenger/Resources/Public/Css/Module.css';
});
