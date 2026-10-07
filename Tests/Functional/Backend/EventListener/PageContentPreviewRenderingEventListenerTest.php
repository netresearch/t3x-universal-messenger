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

namespace Netresearch\UniversalMessenger\Tests\Functional\Backend\EventListener;

use Netresearch\UniversalMessenger\Backend\EventListener\PageContentPreviewRenderingEventListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Tests the page module preview of the "control_structure" content element:
 * it shows the element's body text and the FlexForm replacement text under
 * their translated labels, and leaves every other record alone.
 *
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(PageContentPreviewRenderingEventListener::class)]
final class PageContentPreviewRenderingEventListenerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'netresearch/universal-messenger',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function rendersTheBodyTextAndTheReplacementTextOfAControlStructure(): void
    {
        $event = $this->createEvent(
            'tt_content',
            [
                'CType'       => 'control_structure',
                'bodytext'    => 'Shown to subscribers of the premium list',
                'pi_flexform' => $this->flexFormWithReplacementText('Shown to everybody else'),
            ],
        );

        $this->invokeListener($event);

        $preview = (string) $event->getPreviewContent();

        self::assertMatchesRegularExpression(
            '#<strong>Control Structure</strong>\s*</div>\s*<div>\s*Shown to subscribers of the premium list\s*</div>#',
            $preview,
        );
        self::assertMatchesRegularExpression(
            '#<strong>Alternative text</strong>\s*</div>\s*<div>\s*Shown to everybody else\s*</div>#',
            $preview,
        );
    }

    #[Test]
    public function showsTheTextsWithoutTheirMarkupAsTheCorePreviewDoes(): void
    {
        $event = $this->createEvent(
            'tt_content',
            [
                'CType'       => 'control_structure',
                'bodytext'    => '<p>Premium <em class="x">list</em> & friends</p>',
                'pi_flexform' => $this->flexFormWithReplacementText(
                    htmlspecialchars('<p>Everybody <a href="https://example.org/">else</a></p>'),
                ),
            ],
        );

        $this->invokeListener($event);

        $preview = (string) $event->getPreviewContent();

        self::assertStringContainsString('Premium list &amp; friends', $preview);
        self::assertStringContainsString('Everybody else', $preview);
        self::assertStringNotContainsString('<p>', $preview);
        self::assertStringNotContainsString('<em', $preview);
        self::assertStringNotContainsString('<a ', $preview);
    }

    #[Test]
    public function rendersAnEmptyReplacementTextWhenTheElementHasNoFlexForm(): void
    {
        $event = $this->createEvent(
            'tt_content',
            [
                'CType'       => 'control_structure',
                'bodytext'    => 'Shown to subscribers of the premium list',
                'pi_flexform' => '',
            ],
        );

        $this->invokeListener($event);

        self::assertMatchesRegularExpression(
            '#<strong>Alternative text</strong>\s*</div>\s*<div>\s*</div>#',
            (string) $event->getPreviewContent(),
        );
    }

    #[Test]
    public function leavesOtherContentElementTypesUntouched(): void
    {
        $event = $this->createEvent(
            'tt_content',
            [
                'CType'    => 'text',
                'bodytext' => 'A regular text element',
            ],
        );

        $this->invokeListener($event);

        self::assertNull($event->getPreviewContent());
    }

    #[Test]
    public function leavesRecordsOfOtherTablesUntouched(): void
    {
        $event = $this->createEvent(
            'pages',
            [
                'doktype' => 1,
                'title'   => 'A page',
            ],
        );

        $this->invokeListener($event);

        self::assertNull($event->getPreviewContent());
    }

    private function invokeListener(PageContentPreviewRenderingEvent $event): void
    {
        (new PageContentPreviewRenderingEventListener($this->get(FlexFormTools::class)))($event);
    }

    /**
     * @param array<string, int|string> $row
     */
    private function createEvent(string $table, array $row): PageContentPreviewRenderingEvent
    {
        // Store the row and read it back, so the record carries every column
        // (language, workspace, ...) the page module hands to the event.
        $connection = $this->getConnectionPool()->getConnectionForTable($table);
        $connection->insert($table, array_replace(['pid' => 1], $row));

        $storedRow = BackendUtility::getRecord($table, (int) $connection->lastInsertId());
        self::assertIsArray($storedRow);

        $record = $this->get(RecordFactory::class)->createFromDatabaseRow($table, $storedRow);

        return new PageContentPreviewRenderingEvent(
            $table,
            $record->getRecordType() ?? '',
            $record,
            self::createStub(PageLayoutContext::class),
        );
    }

    private function flexFormWithReplacementText(string $replacementText): string
    {
        return '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>'
            . '<T3FlexForms><data><sheet index="sDEF"><language index="lDEF">'
            . '<field index="settings.replacementBodyText"><value index="vDEF">'
            . $replacementText
            . '</value></field></language></sheet></data></T3FlexForms>';
    }
}
