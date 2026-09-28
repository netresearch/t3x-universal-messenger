<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgSpriteIconProvider;

// The Netresearch module group is shared: nr_textdb (nr_sync only on 13.4)
// registers the same identifier, and the last extension loaded wins. They
// ship ModuleGroup.svg with identical bytes. The module menu renders the icon
// inline, so its currentColor letter follows the backend colour scheme.
return [
    'extension-netresearch-module' => [
        'provider' => SvgIconProvider::class,
        'source'   => 'EXT:universal_messenger/Resources/Public/Icons/ModuleGroup.svg',
    ],
    'extension-netresearch-universal-messenger' => [
        'provider' => BitmapIconProvider::class,
        'source'   => 'EXT:universal_messenger/Resources/Public/Icons/Module.png',
    ],
    // Record icons are sprite icons, the way core registers its own: their
    // markup is <svg><use>, which inherits currentColor. An SvgIconProvider
    // icon is rendered as <img>, where currentColor cannot reach the SVG.
    'universal-messenger-record-newsletterchannel' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite'   => 'EXT:universal_messenger/Resources/Public/Icons/tx_universalmessenger_domain_model_newsletterchannel.svg#tx_universalmessenger_domain_model_newsletterchannel',
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
    ],
];
