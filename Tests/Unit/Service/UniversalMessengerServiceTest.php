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

namespace Netresearch\UniversalMessenger\Tests\Unit\Service;

use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Service\UniversalMessengerService;
use Netresearch\UniversalMessenger\WebserviceConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use RuntimeException;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests that the service only asks TYPO3 for a logger when logging is
 * enabled in the extension settings, and that it hands out one lazily built
 * SDK API entry point. Building the entry point does not contact the
 * webservice; no request is sent by any of these tests.
 *
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(UniversalMessengerService::class)]
final class UniversalMessengerServiceTest extends UnitTestCase
{
    /**
     * @return array<string, array{string|null, bool}>
     */
    public static function enableLoggingSettings(): array
    {
        return [
            'enabled'  => ['1', true],
            'disabled' => ['0', false],
            'empty'    => ['', false],
            'not set'  => [null, false],
        ];
    }

    #[Test]
    #[DataProvider('enableLoggingSettings')]
    public function requestsATypo3LoggerOnlyWhenLoggingIsEnabled(?string $enableLogging, bool $expectLogger): void
    {
        $configuration = self::createStub(Configuration::class);
        $configuration
            ->method('getExtensionSetting')
            ->willReturnMap([
                ['enableLogging', $enableLogging],
                ['apiUrl', 'https://um.example.org/api/'],
                ['apiKey', 'fake-key'],
                ['apiSecret', 'fake-secret'],
            ]);

        $logManager = $this->createMock(LogManager::class);
        $logManager
            ->expects($expectLogger ? self::once() : self::never())
            ->method('getLogger')
            ->with(UniversalMessengerService::class)
            ->willReturn(new NullLogger());

        new UniversalMessengerService(
            $logManager,
            $configuration,
            new WebserviceConfiguration($configuration),
        );
    }

    /**
     * An exception while reading the setting must disable logging instead of
     * breaking the construction of the service.
     */
    #[Test]
    public function disablesLoggingWhenTheSettingCannotBeRead(): void
    {
        $configuration = self::createStub(Configuration::class);
        $configuration
            ->method('getExtensionSetting')
            ->willReturnCallback(static function (string $path): string {
                if ($path === 'enableLogging') {
                    throw new RuntimeException('Extension configuration is not available.');
                }

                return '';
            });

        $logManager = $this->createMock(LogManager::class);
        $logManager
            ->expects(self::never())
            ->method('getLogger');

        new UniversalMessengerService(
            $logManager,
            $configuration,
            self::createStub(WebserviceConfiguration::class),
        );
    }

    #[Test]
    public function returnsTheSameApiEntryPointOnEveryCall(): void
    {
        $configuration = self::createStub(Configuration::class);

        $subject = new UniversalMessengerService(
            self::createStub(LogManager::class),
            $configuration,
            new WebserviceConfiguration($configuration),
        );

        self::assertSame($subject->api(), $subject->api());
    }
}
