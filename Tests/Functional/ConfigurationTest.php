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

namespace Netresearch\UniversalMessenger\Tests\Functional;

use Netresearch\UniversalMessenger\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Tests how the configuration helper reads the extension settings from the
 * real TYPO3 extension configuration and the extbase framework TypoScript
 * from the real configuration manager.
 *
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(Configuration::class)]
final class ConfigurationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'netresearch/universal-messenger',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'universal_messenger' => [
                'storagePageId' => '42',
                'newsletter'    => [
                    'testChannelSuffix' => '_Test',
                ],
            ],
        ],
    ];

    protected function tearDown(): void
    {
        // Leave no request behind for the next test case: the extbase
        // configuration manager falls back to it.
        unset($GLOBALS['TYPO3_REQUEST']);

        parent::tearDown();
    }

    #[Test]
    public function returnsAConfiguredExtensionSetting(): void
    {
        $subject = new Configuration();

        self::assertSame('42', $subject->getExtensionSetting('storagePageId'));
        self::assertSame('_Test', $subject->getExtensionSetting('newsletter/testChannelSuffix'));
    }

    #[Test]
    public function returnsNullForAnExtensionSettingThatIsNotConfigured(): void
    {
        self::assertNull((new Configuration())->getExtensionSetting('newsletter/notDeclaredAnywhere'));
    }

    /**
     * An unset, empty or zero setting falls back to the extension's own
     * newsletter page type 20; any other value is used as configured.
     *
     * @return array<string, array{string|null, int}>
     */
    public static function newsletterPageDokTypes(): array
    {
        return [
            'not configured' => [null, 20],
            'empty'          => ['', 20],
            'zero'           => ['0', 20],
            'custom'         => ['116', 116],
        ];
    }

    #[Test]
    #[DataProvider('newsletterPageDokTypes')]
    public function returnsTheConfiguredNewsletterPageDokTypeOrTheDefault(?string $setting, int $expected): void
    {
        if ($setting !== null) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['universal_messenger']['newsletterPageDokType'] = $setting;
        }

        self::assertSame($expected, (new Configuration())->getNewsletterPageDokType());
    }

    /**
     * The extbase framework configuration always carries a persistence
     * storage PID; a path below it is found, an unknown one is not.
     */
    #[Test]
    public function readsTheFrameworkTypoScriptOfTheCurrentBackendRequest(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.org/typo3/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $subject = new Configuration();

        self::assertTrue($subject->hasTypoScriptSetting('persistence/storagePid'));
        self::assertIsArray($subject->getTypoScriptSetting('persistence'));
        self::assertFalse($subject->hasTypoScriptSetting('view/notConfiguredAnywhere'));
        self::assertNull($subject->getTypoScriptSetting('view/notConfiguredAnywhere'));
    }
}
