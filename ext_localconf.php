<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Controller\NewsletterPreviewController;
use Netresearch\UniversalMessenger\Service\UniversalMessengerService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || exit('Access denied.');

call_user_func(static function (): void {
    // Add TypoScript automatically (to use it in backend modules)
    ExtensionManagementUtility::addTypoScript(
        'universal_messenger',
        'setup',
        '@import "EXT:universal_messenger/Configuration/TypoScript/Default/setup.typoscript"'
    );

    $configuration         = GeneralUtility::makeInstance(Configuration::class);
    $newsletterPageDokType = $configuration->getNewsletterPageDokType();

    // We need to add the following user TypoScript config to all users, so that the new
    // page type is displayed in the wizard
    //
    // In TYPO3 v14, the dynamic configuration must be rebuilt. Currently, you cannot access
    // configured constants.typoscript in the user.tsconfig.
    //
    // See https://forge.typo3.org/issues/106069
    ExtensionManagementUtility::addUserTSConfig(
        'options.pageTree.doktypesToShowInNewPageDragArea := addToList(' . $newsletterPageDokType . ')'
    );

    // Service
    ExtensionManagementUtility::addService(
        'universal_messenger',
        'universal_messenger',
        UniversalMessengerService::class,
        [
            'title'       => 'Universal Messenger API service',
            'description' => 'Universal Messenger API service',
            'subtype'     => '',
            'available'   => true,
            'priority'    => 50,
            'quality'     => 50,
            'os'          => '',
            'exec'        => '',
            'className'   => UniversalMessengerService::class,
        ]
    );

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
