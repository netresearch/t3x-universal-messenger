<?php

/**
 * This file is part of the package netresearch/nrc-universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\NrcUniversalMessenger\Controller\NewsletterPreviewController;
use Netresearch\NrcUniversalMessenger\Service\UniversalMessengerService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || exit('Access denied.');

call_user_func(static function (): void {
    // Add TypoScript automatically (to use it in backend modules)
    ExtensionManagementUtility::addTypoScript(
        'nrc_universal_messenger',
        'constants',
        '@import "EXT:nrc_universal_messenger/Configuration/TypoScript/constants.typoscript"'
    );

    ExtensionManagementUtility::addTypoScript(
        'nrc_universal_messenger',
        'setup',
        '@import "EXT:nrc_universal_messenger/Configuration/TypoScript/setup.typoscript"'
    );

    // Service
    ExtensionManagementUtility::addService(
        'nrc_universal_messenger',
        'nrc_universal_messenger',
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
        'NrcUniversalMessenger',
        'NewsletterPreview',
        [
            NewsletterPreviewController::class => 'preview',
        ],
        [
            NewsletterPreviewController::class => 'preview',
        ],
    );

    // Ignore the following parameters in cHash calculation
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'tx_nrcuniversalmessenger_newsletterpreview[pageId]';
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'type';

    // Add our custom style sheet
    $GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['nrc_universal_messenger']
        = 'EXT:nrc_universal_messenger/Resources/Public/Css/Module.css';
});
