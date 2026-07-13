<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Controller\NewsletterPreviewController;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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

    $configuration         = GeneralUtility::makeInstance(Configuration::class);
    $newsletterPageDokType = $configuration->getNewsletterPageDokType();

    // We need to add the following user TypoScript config to all users, so that the new
    // page type is displayed in the wizard.
    //
    // ExtensionManagementUtility::addUserTSConfig() was removed in TYPO3 v13, so we append
    // to $GLOBALS['TYPO3_CONF_VARS']['BE']['defaultUserTSconfig'] directly. This is the
    // supported way to register dynamic user TSconfig in TYPO3 v13+.
    //
    // In TYPO3 v14, the dynamic configuration must be rebuilt. Currently, you cannot access
    // configured constants.typoscript in the user.tsconfig.
    //
    // See https://forge.typo3.org/issues/106069
    $GLOBALS['TYPO3_CONF_VARS']['BE']['defaultUserTSconfig'] = ($GLOBALS['TYPO3_CONF_VARS']['BE']['defaultUserTSconfig'] ?? '')
        . LF
        . 'options.pageTree.doktypesToShowInNewPageDragArea := addToList(' . $newsletterPageDokType . ')';

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
