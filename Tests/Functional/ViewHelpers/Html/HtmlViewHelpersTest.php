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

namespace Netresearch\UniversalMessenger\Tests\Functional\ViewHelpers\Html;

use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\ViewHelpers\Html\AbstractHtmlViewHelper;
use Netresearch\UniversalMessenger\ViewHelpers\Html\BodyViewHelper;
use Netresearch\UniversalMessenger\ViewHelpers\Html\ColumnViewHelper;
use Netresearch\UniversalMessenger\ViewHelpers\Html\ContainerViewHelper;
use Netresearch\UniversalMessenger\ViewHelpers\Html\RowViewHelper;
use Netresearch\UniversalMessenger\ViewHelpers\Html\SpacerViewHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Renders the e-mail layout view helpers (body, container, row, column,
 * spacer) through Fluid's real view helper invoker and the extension's real
 * view helper templates, and checks the table markup each one produces.
 *
 * The TypoScript-reading Configuration is the only collaborator doubled: it
 * decides whether an integrator configured additional template root paths.
 *
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(AbstractHtmlViewHelper::class)]
#[CoversClass(BodyViewHelper::class)]
#[CoversClass(ColumnViewHelper::class)]
#[CoversClass(ContainerViewHelper::class)]
#[CoversClass(RowViewHelper::class)]
#[CoversClass(SpacerViewHelper::class)]
final class HtmlViewHelpersTest extends FunctionalTestCase
{
    /**
     * @var string
     */
    private const CHILD_CONTENT = '<p>Newsletter child content</p>';

    protected array $testExtensionsToLoad = [
        'netresearch/universal-messenger',
    ];

    #[Test]
    public function bodyWrapsTheChildrenInTheCenteredBodyTable(): void
    {
        $html = $this->render(BodyViewHelper::class);

        self::assertStringContainsString('<table class="body">', $html);
        self::assertStringContainsString(
            '<td class="float-center" align="center" valign="top"> <center> ' . self::CHILD_CONTENT . ' </center>',
            $html,
        );
    }

    #[Test]
    public function containerUsesTheDefaultClassAndRendersTheChildrenUnescaped(): void
    {
        $html = $this->render(ContainerViewHelper::class);

        self::assertStringContainsString(
            '<table align="center" class="container float-center"> <tbody> <tr> <td> ' . self::CHILD_CONTENT . ' </td>',
            $html,
        );
    }

    #[Test]
    public function containerUsesTheGivenClass(): void
    {
        $html = $this->render(ContainerViewHelper::class, ['class' => 'container header']);

        self::assertStringContainsString('<table align="center" class="container header">', $html);
    }

    #[Test]
    public function rowUsesTheDefaultClassAndPlacesTheChildrenInsideTheRow(): void
    {
        $html = $this->render(RowViewHelper::class);

        self::assertStringContainsString(
            '<table class="row"> <tbody> <tr> ' . self::CHILD_CONTENT . ' </tr>',
            $html,
        );
    }

    #[Test]
    public function rowUsesTheGivenClass(): void
    {
        $html = $this->render(RowViewHelper::class, ['class' => 'row collapse']);

        self::assertStringContainsString('<table class="row collapse">', $html);
    }

    #[Test]
    public function spacerUsesTheDefaultClassAndSizeAndRendersNoChildren(): void
    {
        $html = $this->render(SpacerViewHelper::class);

        self::assertStringContainsString('<table class="spacer float-center">', $html);
        self::assertStringContainsString(
            '<td height="16px" style="font-size: 16px; line-height: 16px;">',
            $html,
        );
        self::assertStringNotContainsString(self::CHILD_CONTENT, $html);
    }

    #[Test]
    public function spacerUsesTheGivenClassAndSize(): void
    {
        $html = $this->render(SpacerViewHelper::class, ['class' => 'spacer', 'size' => 40]);

        self::assertStringContainsString('<table class="spacer">', $html);
        self::assertStringContainsString(
            '<td height="40px" style="font-size: 40px; line-height: 40px;">',
            $html,
        );
    }

    /**
     * Column position and count decide the Foundation grid classes when no
     * explicit class is given.
     *
     * @return array<string, array{array<string, int>, string}>
     */
    public static function columnGridClasses(): array
    {
        return [
            'no arguments: a single full-width column' => [[], 'columns small-12 large-12 first last'],
            'first of two columns'                     => [['number' => 1, 'totalNumber' => 2], 'columns small-12 large-6 first'],
            'last of two columns'                      => [['number' => 2, 'totalNumber' => 2], 'columns small-12 large-6 last'],
            'middle of three columns'                  => [['number' => 2, 'totalNumber' => 3], 'columns small-12 large-4'],
            'last of four columns'                     => [['number' => 4, 'totalNumber' => 4], 'columns small-12 large-3 last'],
        ];
    }

    /**
     * @param array<string, int> $arguments
     */
    #[Test]
    #[DataProvider('columnGridClasses')]
    public function columnDerivesTheGridClassesFromItsPosition(array $arguments, string $expectedClass): void
    {
        $html = $this->render(ColumnViewHelper::class, $arguments);

        self::assertStringContainsString('<th class="' . $expectedClass . '">', $html);
        self::assertStringContainsString('<th> ' . self::CHILD_CONTENT . ' </th>', $html);
    }

    #[Test]
    public function columnUsesAnExplicitClassInsteadOfTheGridClasses(): void
    {
        $html = $this->render(
            ColumnViewHelper::class,
            [
                'class'       => '  columns small-12 large-8  ',
                'number'      => 1,
                'totalNumber' => 2,
            ],
        );

        self::assertStringContainsString('<th class="columns small-12 large-8">', $html);
        self::assertStringNotContainsString('first', $html);
        self::assertStringNotContainsString('large-6', $html);
    }

    /**
     * An integrator-configured "view.templateRootPaths" entry gets a
     * "ViewHelpers/" sub-directory appended and takes precedence over the
     * extension's own view helper templates.
     */
    #[Test]
    public function aConfiguredTemplateRootPathOverridesTheExtensionTemplate(): void
    {
        $configuration = self::createStub(Configuration::class);
        $configuration
            ->method('hasTypoScriptSetting')
            ->willReturnMap([
                ['view/templateRootPaths', true],
            ]);
        $configuration
            ->method('getTypoScriptSetting')
            ->willReturnMap([
                [
                    'view/templateRootPaths',
                    [
                        10 => 'EXT:universal_messenger/Tests/Functional/ViewHelpers/Fixtures/Override/',
                    ],
                ],
            ]);

        $html = $this->render(ContainerViewHelper::class, ['class' => 'custom'], $configuration);

        self::assertSame(
            '<div class="override-container custom">' . self::CHILD_CONTENT . '</div>',
            $html,
        );
    }

    /**
     * Invokes the view helper through Fluid's own invoker, which applies the
     * registered argument defaults and validation exactly as a template does,
     * and returns the output with all whitespace runs collapsed to one space.
     *
     * @param class-string<AbstractHtmlViewHelper> $viewHelperClass
     * @param array<string, int|string>            $arguments
     */
    private function render(
        string $viewHelperClass,
        array $arguments = [],
        ?Configuration $configuration = null,
    ): string {
        if (!$configuration instanceof Configuration) {
            $configuration = self::createStub(Configuration::class);
            $configuration
                ->method('hasTypoScriptSetting')
                ->willReturn(false);
        }

        $viewHelper = new $viewHelperClass(
            $this->get(ViewFactoryInterface::class),
            $configuration,
        );

        $renderingContext = $this->get(RenderingContextFactory::class)->create();

        $output = $renderingContext
            ->getViewHelperInvoker()
            ->invoke(
                $viewHelper,
                $arguments,
                $renderingContext,
                static fn (): string => self::CHILD_CONTENT,
            );

        self::assertIsString($output);

        return trim((string) preg_replace('/\s+/', ' ', $output));
    }
}
