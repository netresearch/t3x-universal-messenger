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
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgSpriteIconProvider;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

use function array_keys;
use function hash_file;
use function is_array;

/**
 * Pins the backend icons that must follow, or survive, the backend colour
 * scheme: the module icon, the channel record icon and the Extension Manager
 * tile. They follow the flat TYPO3 v14 style the Netresearch extensions
 * share.
 */
#[CoversNothing]
final class BackendIconsTest extends UnitTestCase
{
    private const ICON_ACCENT = 'var(--nr-icon-accent, #2F99A4)';

    /**
     * Extension.svg is the extension tile: a rounded #2F99A4 tile, the
     * envelope in white and one #FF4D00 accent dot. The Extension Manager
     * shows it as <img>, where currentColor cannot reach the SVG, so it
     * carries fixed colours. It replaces the [n] logo, as in nr_textdb.
     */
    private const EXTENSION_SVG_SHA256 = '5fa628be142cfe50298c6c93de6a913684292377e6dd928e106aa5b575ba0220';

    /**
     * Module.svg is the envelope in currentColor with its flap in the shared
     * accent, so the module menu can draw it in both colour schemes.
     */
    private const MODULE_SVG_SHA256 = 'd27d55817c96f164763a85d89a6540b201be6803fc270fa7422c36ab0fe113de';

    /**
     * The channel record icon is a sprite: a broadcast glyph inside <symbol>,
     * both arcs in currentColor and the source dot in the accent, so core's
     * <svg><use> markup lets it take the surrounding text colour. The arcs
     * stay at full opacity: at 16 px in the record list a faded outer arc
     * was hard to see.
     */
    private const RECORD_SVG_SHA256 = '3ec463118bd28f4ddb77e2a24209a7a804b4443d87326650a62c3b67f66ea802';

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
                'provider' => SvgIconProvider::class,
                'source'   => 'EXT:universal_messenger/Resources/Public/Icons/Module.svg',
            ],
            $icons['extension-netresearch-universal-messenger'] ?? null,
        );
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
    public function extensionIconIsTheWhiteEnvelopeOnTheBrandTile(): void
    {
        $file = self::ICON_DIR . 'Extension.svg';
        $svg  = $this->load($file);

        self::assertSame(['#2F99A4', '#FFFFFF', '#FFFFFF'], $this->pathFills($svg));
        self::assertSame(0, $svg->getElementsByTagName('style')->length);
        self::assertSame(self::EXTENSION_SVG_SHA256, hash_file('sha256', $file));
    }

    #[Test]
    public function moduleIconIsTheEnvelopeInCurrentColorAndTheAccent(): void
    {
        $file = self::ICON_DIR . 'Module.svg';
        $svg  = $this->load($file);

        self::assertSame(['currentColor', self::ICON_ACCENT], $this->pathFills($svg));
        self::assertSame(0, $svg->getElementsByTagName('style')->length);
        self::assertSame(self::MODULE_SVG_SHA256, hash_file('sha256', $file));
    }

    #[Test]
    public function recordIconIsASpriteInCurrentColorAndTheAccent(): void
    {
        $file = self::ICON_DIR . self::RECORD_TABLE . '.svg';
        $svg  = $this->load($file);

        $symbols = $svg->getElementsByTagName('symbol');
        self::assertSame(1, $symbols->length);
        $symbol = $symbols->item(0);
        self::assertInstanceOf(DOMElement::class, $symbol);
        self::assertSame(self::RECORD_TABLE, $symbol->getAttribute('id'));

        self::assertSame(['currentColor', 'currentColor', self::ICON_ACCENT], $this->pathFills($svg));
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
