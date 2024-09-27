<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\UniversalMessenger\Middleware\DecodeCurlyBracesMiddleware;
use Netresearch\UniversalMessenger\Middleware\InlineCssMiddleware;

return [
    'frontend' => [
        'netresearch/universal-messenger/inline-css' => [
            'target' => InlineCssMiddleware::class,
            'after'  => [
                'typo3/cms-frontend/content-length-headers',
            ],
        ],
        'netresearch/universal-messenger/decode-curly-braces' => [
            'target' => DecodeCurlyBracesMiddleware::class,
            // Caution: If you want to modify the response coming from certain middleware, your middleware has to be
            // configured to be before it. Order of processing middlewares when enriching response is opposite to
            // when middlewares are modifying the request.
            'before' => [
                'netresearch/universal-messenger/inline-css',
            ],
        ],
    ],
];
