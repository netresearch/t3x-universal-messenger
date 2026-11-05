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

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgSpriteIconProvider;

return [
    // The module menu renders the module icon inline, so its currentColor
    // glyph follows the backend colour scheme. The accent is
    // var(--nr-icon-accent) with the brand teal as fallback; no stylesheet
    // sets that property yet.
    'extension-netresearch-universal-messenger' => [
        'provider' => SvgIconProvider::class,
        'source'   => 'EXT:universal_messenger/Resources/Public/Icons/Module.svg',
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
