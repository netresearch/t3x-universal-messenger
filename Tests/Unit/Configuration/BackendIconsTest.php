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

namespace Netresearch\UniversalMessenger\Tests\Unit\Configuration;

use DOMDocument;
use DOMElement;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgSpriteIconProvider;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

use function array_keys;
use function hash_file;
use function is_array;

/**
 * Pins the backend icons that must follow, or survive, the backend colour
 * scheme: the channel record icon and the Extension Manager logo.
 */
#[CoversNothing]
final class BackendIconsTest extends UnitTestCase
{
    /**
     * Extension.svg is the Netresearch [n] logo as the netresearch-branding
     * skill specifies it: frame #2F99A4, letter #585961. The Extension
     * Manager shows it as <img>, where currentColor cannot reach the SVG, so
     * it carries fixed brand colours.
     */
    private const EXTENSION_SVG_SHA256 = '43f476aca5ae5e62c9cc59fd178fd84c006de541893b7f7335909587d892504f';

    /**
     * The channel record icon is a sprite: the [n] logo with the frame in the
     * brand teal and the letter in currentColor inside <symbol>, so core's
     * <svg><use> markup lets the letter take the surrounding text colour.
     */
    private const RECORD_SVG_SHA256 = 'ce34f918158de1b9d155523ba67d1ddd67d94fb0169c144fafcb3f744394ca7c';

    private const RECORD_TABLE = 'tx_universalmessenger_domain_model_newsletterchannel';

    private const EXTENSION_ROOT = __DIR__ . '/../../..';

    private const ICON_DIR = self::EXTENSION_ROOT . '/Resources/Public/Icons/';

    #[Test]
    public function iconsAreRegisteredWithTheProviderTheirMarkupNeeds(): void
    {
        // No other code in the unit suite includes Icons.php, and this is the
        // only test that does. Should that change, require_once returns true
        // and the next assertion fails.
        $icons = require_once self::EXTENSION_ROOT . '/Configuration/Icons.php';

        self::assertTrue(is_array($icons));
        self::assertArrayNotHasKey('extension-netresearch-module', $icons);
        self::assertSame(
            [
                'provider' => SvgSpriteIconProvider::class,
                'sprite'   => 'EXT:universal_messenger/Resources/Public/Icons/' . self::RECORD_TABLE . '.svg#' . self::RECORD_TABLE,
            ],
            $icons['universal-messenger-record-newsletterchannel'] ?? null,
        );
    }

    /**
     * The module sits in the core "Content" group instead of a top-level
     * Netresearch group of its own.
     */
    #[Test]
    public function moduleIsASubmoduleOfTheContentGroup(): void
    {
        // Only this test includes Modules.php; a second include would make
        // require_once return true and fail the next assertion.
        $modules = require_once self::EXTENSION_ROOT . '/Configuration/Backend/Modules.php';

        self::assertTrue(is_array($modules));
        self::assertSame(['netresearch_universal_messenger'], array_keys($modules));
        self::assertSame('content', $modules['netresearch_universal_messenger']['parent'] ?? null);
    }

    #[Test]
    public function extensionLogoIsTheBrandLogoInFixedBrandColours(): void
    {
        $file = self::ICON_DIR . 'Extension.svg';
        $svg  = $this->load($file);

        self::assertSame(['#2F99A4', '#585961'], $this->pathFills($svg));
        self::assertSame(0, $svg->getElementsByTagName('style')->length);
        self::assertSame(self::EXTENSION_SVG_SHA256, hash_file('sha256', $file));
    }

    #[Test]
    public function recordIconIsASpriteWithACurrentColorLetter(): void
    {
        $file = self::ICON_DIR . self::RECORD_TABLE . '.svg';
        $svg  = $this->load($file);

        $symbols = $svg->getElementsByTagName('symbol');
        self::assertSame(1, $symbols->length);
        $symbol = $symbols->item(0);
        self::assertInstanceOf(DOMElement::class, $symbol);
        self::assertSame(self::RECORD_TABLE, $symbol->getAttribute('id'));

        self::assertSame(['#2F99A4', 'currentColor'], $this->pathFills($svg));
        self::assertSame(0, $svg->getElementsByTagName('style')->length);
        self::assertSame(self::RECORD_SVG_SHA256, hash_file('sha256', $file));
    }

    private function load(string $file): DOMElement
    {
        $document = new DOMDocument();
        self::assertTrue($document->load($file));
        $svg = $document->documentElement;
        self::assertInstanceOf(DOMElement::class, $svg);

        return $svg;
    }

    /**
     * @return list<string>
     */
    private function pathFills(DOMElement $svg): array
    {
        $fills = [];
        foreach ($svg->getElementsByTagName('path') as $path) {
            $fills[] = $path->getAttribute('fill');
        }

        return $fills;
    }
}
