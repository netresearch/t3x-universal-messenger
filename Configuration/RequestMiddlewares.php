<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\UniversalMessenger\Middleware\InlineCssMiddleware;

return [
    'frontend' => [
        'universal-messenger/inline-css' => [
            'target' => InlineCssMiddleware::class,
            'after'  => [
                'typo3/cms-frontend/content-length-headers',
            ],
        ],
    ],
];
