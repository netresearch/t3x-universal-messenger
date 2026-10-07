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

namespace Netresearch\UniversalMessenger\Tests\Unit\Backend\EventListener;

use Netresearch\UniversalMessenger\Backend\EventListener\ModifyBlindedConfigurationOptionsEventListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Lowlevel\Event\ModifyBlindedConfigurationOptionsEvent;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests that the API key and secret are masked in the TYPO3_CONF_VARS view
 * of the "Configuration" module, and that no other configuration provider
 * is touched.
 *
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(ModifyBlindedConfigurationOptionsEventListener::class)]
final class ModifyBlindedConfigurationOptionsEventListenerTest extends UnitTestCase
{
    #[Test]
    public function masksTheApiCredentialsAndKeepsOptionsBlindedByOthers(): void
    {
        $event = new ModifyBlindedConfigurationOptionsEvent(
            [
                'TYPO3_CONF_VARS' => [
                    'DB' => [
                        'Connections' => [
                            'Default' => [
                                'password' => '******',
                            ],
                        ],
                    ],
                ],
            ],
            'confVars',
        );

        (new ModifyBlindedConfigurationOptionsEventListener())($event);

        self::assertSame(
            [
                'TYPO3_CONF_VARS' => [
                    'DB' => [
                        'Connections' => [
                            'Default' => [
                                'password' => '******',
                            ],
                        ],
                    ],
                    'EXTENSIONS' => [
                        'universal_messenger' => [
                            'apiKey'    => '******',
                            'apiSecret' => '******',
                        ],
                    ],
                ],
            ],
            $event->getBlindedConfigurationOptions(),
        );
    }

    #[Test]
    public function leavesOtherConfigurationProvidersUnchanged(): void
    {
        $blindedOptions = [
            'SomeOtherProvider' => [
                'secret' => '******',
            ],
        ];

        $event = new ModifyBlindedConfigurationOptionsEvent($blindedOptions, 'siteConfiguration');

        (new ModifyBlindedConfigurationOptionsEventListener())($event);

        self::assertSame($blindedOptions, $event->getBlindedConfigurationOptions());
    }
}
