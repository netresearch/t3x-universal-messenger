<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\UniversalMessenger\Controller\UniversalMessengerController;

// Caution, variable name must not exist within \TYPO3\CMS\Core\Package\AbstractServiceProvider::configureBackendModules
return [
    'netresearch_module' => [
        'labels'         => 'LLL:EXT:universal_messenger/Resources/Private/Language/locallang_mod.xlf',
        'iconIdentifier' => 'extension-netresearch-module',
        'position'       => [
            'after' => 'web',
        ],
    ],
    'netresearch_universal_messenger' => [
        'parent'                                   => 'netresearch_module',
        'position'                                 => [],
        'access'                                   => 'user',
        'iconIdentifier'                           => 'extension-netresearch-universal-messenger',
        'path'                                     => '/module/netresearch/universal-messenger',
        'labels'                                   => 'LLL:EXT:universal_messenger/Resources/Private/Language/locallang_mod_um.xlf',
        'extensionName'                            => 'UniversalMessenger',
        'inheritNavigationComponentFromMainModule' => false,
        'navigationComponent'                      => '@typo3/backend/page-tree/page-tree-element',
        'controllerActions'                        => [
            UniversalMessengerController::class => [
                'index',
                'create',
            ],
        ],
        'moduleData' => [
            'language' => 0,
        ],
    ],
];
