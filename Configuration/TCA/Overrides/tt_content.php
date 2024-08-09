<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || exit('Access denied.');

call_user_func(static function (): void {
    // Add content element group to selector list
    ExtensionManagementUtility::addTcaSelectItemGroup(
        'tt_content',
        'CType',
        'universal_messenger',
        'LLL:EXT:universal_messenger/Resources/Private/Language/Backend.xlf:content_group.universalmessenger'
    );

    // Add content element
    if (!is_array($GLOBALS['TCA']['tt_content']['types']['control_structure'] ?? false)) {
        $GLOBALS['TCA']['tt_content']['types']['control_structure'] = [];
    }

    // Add content element PageTSConfig
    ExtensionManagementUtility::registerPageTSConfigFile(
        'universal_messenger',
        'Configuration/TsConfig/Page/ContentElement/Element/ControlStructure.tsconfig',
        'Universal Messenger Content Element: Control Structure'
    );

    // Add content element to selector list
    ExtensionManagementUtility::addTcaSelectItem(
        'tt_content',
        'CType',
        [
            'label' => 'LLL:EXT:universal_messenger/Resources/Private/Language/Backend.xlf:content_element.control_structure',
            'value' => 'control_structure',
            'icon'  => 'content-universalmessenger-controlstructure',
            'group' => 'universal_messenger',
        ]
    );

    ArrayUtility::mergeRecursiveWithOverrule(
        $GLOBALS['TCA']['tt_content'],
        [
            // Add the icon to the icon class configuration
            'ctrl' => [
                'typeicon_classes' => [
                    'control_structure' => 'content-universalmessenger-controlstructure',
                ],
            ],

            // Configure element type
            'types' => [
                'control_structure' => [
                    'showitem' => '
                        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                            --palette--;;general,
                            header;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:header.ALT.html_formlabel,
                            bodytext;LLL:EXT:universal_messenger/Resources/Private/Language/Backend.xlf:content_element.control_structure.bodytext,
                            pi_flexform,
                        --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.appearance,
                            --palette--;;appearanceLinks,
                        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                            --palette--;;hidden,
                            --palette--;;access,',

                    'columnsOverrides' => [
                        'bodytext' => [
                            'description' => 'LLL:EXT:universal_messenger/Resources/Private/Language/Backend.xlf:content_element.control_structure.bodytext.description',
                            'config'      => [
                                'enableRichtext' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ]
    );

    // Add flexForms for content element configuration
    ExtensionManagementUtility::addPiFlexFormValue(
        '*',
        'FILE:EXT:universal_messenger/Configuration/FlexForms/ControlStructure.xml',
        'control_structure'
    );
});
