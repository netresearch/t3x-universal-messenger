<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Functional\Configuration;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Verifies that the extension's TCA overrides for the "pages" table are applied
 * once the extension is loaded in a real TYPO3 instance.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
final class PagesTcaTest extends FunctionalTestCase
{
    /**
     * @var non-empty-string[]
     */
    protected array $testExtensionsToLoad = [
        'netresearch/universal-messenger',
    ];

    #[Test]
    public function newsletterChannelColumnIsRegisteredOnPages(): void
    {
        self::assertArrayHasKey(
            'universal_messenger_channel',
            $GLOBALS['TCA']['pages']['columns'],
            'The extension should register the "universal_messenger_channel" column on the pages table.',
        );
    }

    #[Test]
    public function newsletterDoktypeIsAddedToThePageTypeSelector(): void
    {
        $doktypeItems = $GLOBALS['TCA']['pages']['columns']['doktype']['config']['items'] ?? [];

        $registeredValues = array_map(
            static fn (array $item): int => (int) ($item['value'] ?? 0),
            $doktypeItems,
        );

        // The default newsletter page type value is 20 (ext_conf_template.txt).
        self::assertContains(
            20,
            $registeredValues,
            'The newsletter page type (doktype 20) should be registered in the pages doktype selector.',
        );
    }
}
