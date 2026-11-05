<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 * SPDX-License-Identifier: LicenseRef-Netresearch-Restricted-Use
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\UniversalMessenger\Controller\UniversalMessengerController;

// Caution, variable name must not exist within \TYPO3\CMS\Core\Package\AbstractServiceProvider::configureBackendModules
return [
    // Editors send newsletter pages from here, so the module sits in the core
    // "Content" group next to the other page-tree modules. The identifier and
    // path stay unchanged: group permissions and bookmarks refer to them.
    'netresearch_universal_messenger' => [
        'parent'                                   => 'content',
        'position'                                 => [],
        'access'                                   => 'user',
        'iconIdentifier'                           => 'extension-netresearch-universal-messenger',
        'path'                                     => '/module/netresearch/universal-messenger',
        'labels'                                   => 'LLL:EXT:universal_messenger/Resources/Private/Language/locallang_mod_um.xlf',
        'extensionName'                            => 'UniversalMessenger',
        'inheritNavigationComponentFromMainModule' => false,
        'navigationComponent'                      => '@typo3/backend/tree/page-tree-element',
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
