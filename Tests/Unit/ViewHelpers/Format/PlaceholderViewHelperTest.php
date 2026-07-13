<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit\ViewHelpers\Format;

use Netresearch\UniversalMessenger\ViewHelpers\Format\PlaceholderViewHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests that the placeholder view helper wraps a value in curly braces.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(PlaceholderViewHelper::class)]
final class PlaceholderViewHelperTest extends UnitTestCase
{
    /**
     * @return array<string, array{value: string, expected: string}>
     */
    public static function valueProvider(): array
    {
        return [
            'simple identifier' => [
                'value'    => 'identifier',
                'expected' => '{identifier}',
            ],
            'value with dots' => [
                'value'    => 'user.email',
                'expected' => '{user.email}',
            ],
            'empty value' => [
                'value'    => '',
                'expected' => '{}',
            ],
        ];
    }

    #[Test]
    #[DataProvider('valueProvider')]
    public function renderWrapsTheValueInCurlyBraces(string $value, string $expected): void
    {
        $viewHelper = new PlaceholderViewHelper();
        $viewHelper->setArguments(['value' => $value]);

        self::assertSame($expected, $viewHelper->render());
    }
}
