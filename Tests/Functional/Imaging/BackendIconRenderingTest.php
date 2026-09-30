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

namespace Netresearch\UniversalMessenger\Tests\Functional\Imaging;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Icons that draw in currentColor must reach the backend as inline SVG or as
 * <svg><use>. As <img> the SVG cannot see currentColor and paints the glyph
 * black, which disappears on the dark scheme.
 */
#[CoversNothing]
final class BackendIconRenderingTest extends FunctionalTestCase
{
    private const RECORD_TABLE = 'tx_universalmessenger_domain_model_newsletterchannel';

    /**
     * @var non-empty-string[]
     */
    protected array $testExtensionsToLoad = [
        'netresearch/universal-messenger',
    ];

    /**
     * The module menu renders the group icon with the "inline" markup, so
     * the letter of the [n] logo follows the scheme while the frame keeps
     * the brand teal.
     */
    #[Test]
    public function moduleGroupIconInlineMarkupDrawsTheLetterInCurrentColor(): void
    {
        $icon = $this->get(IconFactory::class)->getIcon('extension-netresearch-module', IconSize::MEDIUM);

        $markup = $icon->getAlternativeMarkup('inline');

        self::assertStringContainsString('<svg', $markup);
        self::assertStringContainsString('fill="currentColor"', $markup);
        self::assertStringContainsString('fill="#2F99A4"', $markup);
    }

    #[Test]
    public function channelRecordIconRendersAsSpriteUseInBothMarkups(): void
    {
        $icon = $this->get(IconFactory::class)->getIconForRecord(self::RECORD_TABLE, ['uid' => 1, 'pid' => 0], IconSize::SMALL);

        self::assertSame('universal-messenger-record-newsletterchannel', $icon->getIdentifier());

        foreach (['default' => $icon->render(), 'inline' => $icon->render('inline')] as $variant => $markup) {
            self::assertStringNotContainsString('<img', $markup, $variant . ' markup');
            self::assertMatchesRegularExpression(
                // TYPO3 inserts a cache-busting query string before the fragment.
                '/<use [^>]*href="[^"]*' . self::RECORD_TABLE . '\.svg(\?[^"#]*)?#' . self::RECORD_TABLE . '"/',
                $markup,
                $variant . ' markup',
            );
        }
    }
}
