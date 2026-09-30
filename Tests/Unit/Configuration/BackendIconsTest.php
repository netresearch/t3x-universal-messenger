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

use function hash_file;
use function is_array;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgSpriteIconProvider;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Pins the backend icons that must follow, or survive, the backend colour
 * scheme: the shared module group icon, the channel record icon and the
 * Extension Manager logo.
 */
#[CoversNothing]
final class BackendIconsTest extends UnitTestCase
{
    /**
     * The module group icon "extension-netresearch-module" is registered by
     * universal_messenger and nr_textdb (nr_sync only on 13.4). The last
     * extension loaded wins, so they ship the same bytes; the same hash is
     * pinned in nr_textdb and nr_sync.
     */
    private const MODULE_GROUP_SVG_SHA256 = 'f61031fd7d3f9b73f28bd4e05b5b398dca87e92a645dc03ad1daa3ccf54524c5';

    private const MODULE_GROUP_SVG = <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 300 300">
            <path fill="#2F99A4" d="M209.6,0V31.62h32.77a26.38,26.38,0,0,1,26.44,26.43V242a26.38,26.38,0,0,1-26.44,26.44H209.6V300h47.93a42.77,42.77,0,0,0,42.86-42.86V42.89A42.76,42.76,0,0,0,257.53,0ZM43.25,0A42.76,42.76,0,0,0,.39,42.89V257.18A42.76,42.76,0,0,0,43.25,300H91.18V268.46H58.4A26.38,26.38,0,0,1,32,242v-184A26.37,26.37,0,0,1,58.4,31.62H91.18V0Z" transform="translate(-0.39 -0.04)"/>
            <path fill="currentColor" d="M221.44,120.41c0-34.48-13.94-57.82-48.93-57.82-26.62,0-48.54,7.74-64.17,26.56l-.7-22.06-28.31.06V232.94h31.59V124.69c7.14-18.38,32.14-34.8,53-34.5,27.38.4,25.2,26.24,26,45.81v96.94h31.58" transform="translate(-0.39 -0.04)"/>
        </svg>

        SVG;

    /**
     * Extension.svg is the Netresearch [n] logo as the netresearch-branding
     * skill specifies it: frame #2F99A4, letter #585961. The Extension
     * Manager shows it as <img>, where currentColor cannot reach the SVG, so
     * it carries fixed brand colours. Same bytes as nr_textdb's Extension.svg.
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
        self::assertSame(
            [
                'provider' => SvgIconProvider::class,
                'source'   => 'EXT:universal_messenger/Resources/Public/Icons/ModuleGroup.svg',
            ],
            $icons['extension-netresearch-module'] ?? null,
        );
        self::assertSame(
            [
                'provider' => SvgSpriteIconProvider::class,
                'sprite'   => 'EXT:universal_messenger/Resources/Public/Icons/' . self::RECORD_TABLE . '.svg#' . self::RECORD_TABLE,
            ],
            $icons['universal-messenger-record-newsletterchannel'] ?? null,
        );
    }

    #[Test]
    public function groupModuleUsesTheSharedGroupIcon(): void
    {
        // Only this test includes Modules.php; a second include would make
        // require_once return true and fail the next assertion.
        $modules = require_once self::EXTENSION_ROOT . '/Configuration/Backend/Modules.php';

        self::assertTrue(is_array($modules));
        self::assertSame('extension-netresearch-module', $modules['netresearch_module']['iconIdentifier'] ?? null);
    }

    #[Test]
    public function moduleGroupIconHasTheSharedBytes(): void
    {
        $file = self::ICON_DIR . 'ModuleGroup.svg';

        self::assertStringEqualsFile($file, self::MODULE_GROUP_SVG);
        self::assertSame(self::MODULE_GROUP_SVG_SHA256, hash_file('sha256', $file));
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
