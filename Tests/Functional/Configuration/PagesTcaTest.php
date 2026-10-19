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

namespace Netresearch\UniversalMessenger\Tests\Functional\Configuration;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Verifies that the extension's TCA overrides for the "pages" table are applied
 * once the extension is loaded in a real TYPO3 instance.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license LicenseRef-Netresearch-Restricted-Use
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
            $this->getPagesColumns(),
            'The extension should register the "universal_messenger_channel" column on the pages table.',
        );
    }

    #[Test]
    public function newsletterDoktypeIsAddedToThePageTypeSelector(): void
    {
        $doktypeColumn = $this->getPagesColumns()['doktype'] ?? null;
        self::assertIsArray($doktypeColumn);
        self::assertIsArray($doktypeColumn['config'] ?? null);

        $doktypeItems = $doktypeColumn['config']['items'] ?? [];
        self::assertIsArray($doktypeItems);

        $registeredValues = [];

        foreach ($doktypeItems as $item) {
            self::assertIsArray($item);
            self::assertIsNumeric($item['value'] ?? null);

            $registeredValues[] = (int) $item['value'];
        }

        // The default newsletter page type value is 20 (ext_conf_template.txt).
        self::assertContains(
            20,
            $registeredValues,
            'The newsletter page type (doktype 20) should be registered in the pages doktype selector.',
        );
    }

    /**
     * Returns the column configuration of the pages table.
     *
     * @return array<array-key, mixed>
     */
    private function getPagesColumns(): array
    {
        $tca = $GLOBALS['TCA'] ?? null;
        self::assertIsArray($tca);
        self::assertIsArray($tca['pages'] ?? null);
        self::assertIsArray($tca['pages']['columns'] ?? null);

        return $tca['pages']['columns'];
    }
}
