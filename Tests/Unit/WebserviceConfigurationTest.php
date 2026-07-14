<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit;

use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\WebserviceConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests that the webservice configuration exposes the API URL, key and secret.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(WebserviceConfiguration::class)]
final class WebserviceConfigurationTest extends UnitTestCase
{
    #[Test]
    public function exposesTheConfiguredWebserviceCredentials(): void
    {
        $configuration = self::createStub(Configuration::class);
        $configuration
            ->method('getExtensionSetting')
            ->willReturnMap([
                ['apiUrl', 'https://example.org/p'],
                ['apiKey', 'public-key'],
                ['apiSecret', 'secret-key'],
            ]);

        $subject = new WebserviceConfiguration($configuration);

        self::assertSame('https://example.org/p', $subject->getApiBaseUrl());
        self::assertSame('public-key', $subject->getApiKey());
        self::assertSame('secret-key', $subject->getApiSecret());
    }

    #[Test]
    public function fallsBackToEmptyStringsWhenNothingIsConfigured(): void
    {
        $configuration = self::createStub(Configuration::class);
        $configuration
            ->method('getExtensionSetting')
            ->willReturn(null);

        $subject = new WebserviceConfiguration($configuration);

        self::assertSame('', $subject->getApiBaseUrl());
        self::assertSame('', $subject->getApiKey());
        self::assertSame('', $subject->getApiSecret());
    }
}
