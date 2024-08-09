<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'extension-netresearch-module' => [
        'provider' => SvgIconProvider::class,
        'source'   => 'EXT:universal_messenger/Resources/Public/Icons/Module.svg',
    ],
    'extension-netresearch-universal-messenger' => [
        'provider' => BitmapIconProvider::class,
        'source'   => 'EXT:universal_messenger/Resources/Public/Icons/Extension.png',
    ],
    'universal-messenger-dok-type-newsletter' => [
        'provider' => SvgIconProvider::class,
        'source'   => 'EXT:universal_messenger/Resources/Public/Icons/DokTypeNewsletter.svg',
    ],

    // Content elements
    //
    // Using more than two hyphens in the identifier will fail with rendering the icon in some places
    'content-universalmessenger-controlstructure' => [
        'provider' => SvgIconProvider::class,
        'source'   => 'EXT:core/Resources/Public/Icons/T3Icons/svgs/content/content-special-html.svg',
    ]
];
