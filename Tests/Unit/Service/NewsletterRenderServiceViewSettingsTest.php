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
use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use Netresearch\UniversalMessenger\Tests\Unit\TrustedServerRequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The view of the newsletter container takes its paths from TypoScript. A
 * setting of the wrong kind, e.g. "view.templateRootPaths = EXT:…" without
 * an index, counts as not configured instead of ending the rendering with
 * a TypeError in the ViewFactoryData constructor.
 *
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(NewsletterRenderService::class)]
final class NewsletterRenderServiceViewSettingsTest extends UnitTestCase
{
    use NewsletterContentResponseTrait;
    use TrustedServerRequestTrait;

    private const TEMPLATE_ROOT_PATH = 'EXT:universal_messenger/Resources/Private/Templates/';

    private const PARTIAL_ROOT_PATH = 'EXT:universal_messenger/Resources/Private/Partials/';

    private const LAYOUT_ROOT_PATH = 'EXT:universal_messenger/Resources/Private/Layouts/';

    private const TEMPLATE_PATH_AND_FILENAME = 'EXT:universal_messenger/Resources/Private/Templates/Container.html';

    private ?ViewFactoryData $viewFactoryData = null;

    #[Test]
    public function passesViewSettingsOfTheWrongKindAsNotConfigured(): void
    {
        $subject = $this->createSubject([
            'view/templateRootPaths'       => self::TEMPLATE_ROOT_PATH,
            'view/partialRootPaths'        => self::PARTIAL_ROOT_PATH,
            'view/layoutRootPaths'         => self::LAYOUT_ROOT_PATH,
            'view/templatePathAndFilename' => [self::TEMPLATE_PATH_AND_FILENAME],
        ]);

        $subject->renderNewsletterPreviewPage(
            $this->createTrustedServerRequest('https://example.com/newsletter?pageId=42'),
            42,
        );

        self::assertInstanceOf(ViewFactoryData::class, $this->viewFactoryData);
        self::assertNull($this->viewFactoryData->templateRootPaths);
        self::assertNull($this->viewFactoryData->partialRootPaths);
        self::assertNull($this->viewFactoryData->layoutRootPaths);
        self::assertNull($this->viewFactoryData->templatePathAndFilename);
    }

    #[Test]
    public function passesConfiguredViewSettingsUnchanged(): void
    {
        $subject = $this->createSubject([
            'view/templateRootPaths'       => [10 => self::TEMPLATE_ROOT_PATH],
            'view/partialRootPaths'        => [10 => self::PARTIAL_ROOT_PATH],
            'view/layoutRootPaths'         => [10 => self::LAYOUT_ROOT_PATH],
            'view/templatePathAndFilename' => self::TEMPLATE_PATH_AND_FILENAME,
        ]);

        $subject->renderNewsletterPreviewPage(
            $this->createTrustedServerRequest('https://example.com/newsletter?pageId=42'),
            42,
        );

        self::assertInstanceOf(ViewFactoryData::class, $this->viewFactoryData);
        self::assertSame([10 => self::TEMPLATE_ROOT_PATH], $this->viewFactoryData->templateRootPaths);
        self::assertSame([10 => self::PARTIAL_ROOT_PATH], $this->viewFactoryData->partialRootPaths);
        self::assertSame([10 => self::LAYOUT_ROOT_PATH], $this->viewFactoryData->layoutRootPaths);
        self::assertSame(self::TEMPLATE_PATH_AND_FILENAME, $this->viewFactoryData->templatePathAndFilename);
    }

    /**
     * @param array<string, mixed> $typoScriptSettings the TypoScript settings by path
     */
    private function createSubject(array $typoScriptSettings): NewsletterRenderService
    {
        $routerStub = self::createStub(RouterInterface::class);
        $routerStub
            ->method('generateUri')
            ->willReturn(new Uri('https://example.com/some-page?type=1716283827&_language=0'));

        $siteStub = self::createStub(Site::class);
        $siteStub
            ->method('getRouter')
            ->willReturn($routerStub);

        $siteFinderStub = self::createStub(SiteFinder::class);
        $siteFinderStub
            ->method('getSiteByPageId')
            ->willReturn($siteStub);

        $requestFactoryStub = self::createStub(RequestFactory::class);
        $requestFactoryStub
            ->method('request')
            ->willReturn($this->createNewsletterContentResponse());

        $viewStub = self::createStub(ViewInterface::class);
        $viewStub
            ->method('assign')
            ->willReturnSelf();
        $viewStub
            ->method('render')
            ->willReturn('rendered container');

        $viewFactoryStub = self::createStub(ViewFactoryInterface::class);
        $viewFactoryStub
            ->method('create')
            ->willReturnCallback(
                function (ViewFactoryData $viewFactoryData) use ($viewStub): ViewInterface {
                    $this->viewFactoryData = $viewFactoryData;

                    return $viewStub;
                },
            );

        $configurationStub = self::createStub(Configuration::class);
        $configurationStub
            ->method('getTypoScriptSetting')
            ->willReturnCallback(
                static fn (string $path): mixed => $typoScriptSettings[$path] ?? null,
            );

        return new NewsletterRenderService(
            $requestFactoryStub,
            $siteFinderStub,
            $viewFactoryStub,
            $configurationStub,
        );
    }
}
