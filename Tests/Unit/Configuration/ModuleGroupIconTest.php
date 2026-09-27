<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit\Configuration;

use function hash_file;
use function is_array;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The module group icon "extension-netresearch-module" is registered by
 * universal_messenger, nr_textdb and nr_sync. The last extension loaded wins,
 * so all three must ship the same bytes: the Netresearch [n] logo with the
 * frame in the brand teal and the letter in currentColor, which the module
 * menu renders inline so the letter follows the backend colour scheme.
 */
#[CoversNothing]
final class ModuleGroupIconTest extends UnitTestCase
{
    /**
     * The same hash is pinned in nr_textdb and nr_sync.
     */
    private const MODULE_GROUP_SVG_SHA256 = 'f61031fd7d3f9b73f28bd4e05b5b398dca87e92a645dc03ad1daa3ccf54524c5';

    private const MODULE_GROUP_SVG = <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 300 300">
            <path fill="#2F99A4" d="M209.6,0V31.62h32.77a26.38,26.38,0,0,1,26.44,26.43V242a26.38,26.38,0,0,1-26.44,26.44H209.6V300h47.93a42.77,42.77,0,0,0,42.86-42.86V42.89A42.76,42.76,0,0,0,257.53,0ZM43.25,0A42.76,42.76,0,0,0,.39,42.89V257.18A42.76,42.76,0,0,0,43.25,300H91.18V268.46H58.4A26.38,26.38,0,0,1,32,242v-184A26.37,26.37,0,0,1,58.4,31.62H91.18V0Z" transform="translate(-0.39 -0.04)"/>
            <path fill="currentColor" d="M221.44,120.41c0-34.48-13.94-57.82-48.93-57.82-26.62,0-48.54,7.74-64.17,26.56l-.7-22.06-28.31.06V232.94h31.59V124.69c7.14-18.38,32.14-34.8,53-34.5,27.38.4,25.2,26.24,26,45.81v96.94h31.58" transform="translate(-0.39 -0.04)"/>
        </svg>

        SVG;

    private const EXTENSION_ROOT = __DIR__ . '/../../..';

    #[Test]
    public function moduleGroupIconIsRegisteredAsInlineCapableSvg(): void
    {
        $icons = require self::EXTENSION_ROOT . '/Configuration/Icons.php';

        self::assertTrue(is_array($icons));
        self::assertSame(
            [
                'provider' => SvgIconProvider::class,
                'source'   => 'EXT:universal_messenger/Resources/Public/Icons/ModuleGroup.svg',
            ],
            $icons['extension-netresearch-module'] ?? null,
        );
    }

    #[Test]
    public function moduleGroupIconHasTheSharedBytes(): void
    {
        $file = self::EXTENSION_ROOT . '/Resources/Public/Icons/ModuleGroup.svg';

        self::assertStringEqualsFile($file, self::MODULE_GROUP_SVG);
        self::assertSame(self::MODULE_GROUP_SVG_SHA256, hash_file('sha256', $file));
    }
}
