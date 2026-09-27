<?php

/*
 * This file is part of the package netresearch/universal-messenger.
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
 * The module menu renders the group icon with the "inline" markup. Only
 * inline SVG inherits currentColor, so the letter of the [n] logo follows the
 * backend colour scheme while the frame keeps the brand teal.
 */
#[CoversNothing]
final class ModuleGroupIconRenderingTest extends FunctionalTestCase
{
    /**
     * @var non-empty-string[]
     */
    protected array $testExtensionsToLoad = [
        'netresearch/universal-messenger',
    ];

    #[Test]
    public function moduleGroupIconInlineMarkupDrawsTheLetterInCurrentColor(): void
    {
        $icon = $this->get(IconFactory::class)->getIcon('extension-netresearch-module', IconSize::MEDIUM);

        $markup = $icon->getAlternativeMarkup('inline');

        self::assertStringContainsString('<svg', $markup);
        self::assertStringContainsString('fill="currentColor"', $markup);
        self::assertStringContainsString('fill="#2F99A4"', $markup);
    }
}
