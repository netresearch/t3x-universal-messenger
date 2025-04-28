<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

$EM_CONF['universal_messenger'] = [
    'title'          => 'Netresearch: Universal Messenger',
    'description'    => 'TYPO3 extension providing a backend module to send newsletters using Universal Messenger API',
    'category'       => 'module',
    'author'         => 'Rico Sonntag',
    'author_email'   => 'rico.sonntag@netresearch.de',
    'author_company' => 'Netresearch DTT GmbH',
    'state'          => 'stable',
    'version'        => '2.0.2',
    'constraints'    => [
        'depends' => [
            'typo3' => '12.4.0-12.99.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
