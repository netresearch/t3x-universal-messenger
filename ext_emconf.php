<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

$EM_CONF['universal_messenger'] = [
    'title'          => 'Netresearch: Universal Messenger',
    'description'    => 'TYPO3 extension providing a backend module to send newsletters using Universal Messenger API',
    'category'       => 'module',
    'author'         => 'Rico Sonntag',
    'author_email'   => 'rico.sonntag@netresearch.de',
    'author_company' => 'Netresearch DTT GmbH',
    'state'          => 'stable',
    'version'        => '3.0.1',
    'constraints'    => [
        'depends' => [
            'typo3' => '14.0.0-14.99.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
