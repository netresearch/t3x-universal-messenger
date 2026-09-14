<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit\Service;

use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * A site configured with a relative base (base: /), a documented option for a single-domain
 * TYPO3 v14 site, makes Router::generateUri() return a host-less URI. renderByPageId() used
 * to pass that URI straight to isUrlValid() (FILTER_VALIDATE_URL, which requires an absolute
 * URL), always failing and throwing "Preview URL is invalid: ..." before the self-fetch could
 * even happen. This mirrors UniversalMessengerController::getNewsletterUrl()'s existing
 * host-less-URI fallback, both now delegating to the shared Utility\UriUtility::
 * resolveAbsoluteUri().
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(NewsletterRenderService::class)]
final class NewsletterRenderServiceRelativeBaseTest extends UnitTestCase
{
    /**
     * Router::generateUri() returning a host-less URI (the relative-base symptom) must
     * still result in the newsletter content being fetched, over an absolute URL built
     * from the current frontend request's own scheme/host/port. Without the fallback,
     * NewsletterRenderService::isUrlValid() rejects the host-less URI and this test
     * fails with an uncaught RuntimeException instead of reaching the assertion below.
     */
    #[Test]
    public function resolvesAHostLessPageUriToAnAbsoluteOneBeforeFetchingIt(): void
    {
        $routerStub = self::createStub(RouterInterface::class);
        $routerStub
            ->method('generateUri')
            ->willReturn(new Uri('/some-page?type=1716283827&_language=0'));

        $subject = $this->createSubject(
            $routerStub,
            'https://example.com:8443/some-page?type=1716283827&_language=0',
        );

        $serverRequest = new ServerRequest('https://example.com:8443/newsletter?pageId=42');

        self::assertSame(
            'rendered container',
            $subject->renderNewsletterPreviewPage(
                $serverRequest,
                42,
            ),
        );
    }

    /**
     * The fallback must apply only to a host-less URI, never unconditionally. A router
     * already returning an absolute URI (a normal, absolute-base site) carries its own
     * correct domain, which may differ from the domain of the request currently being
     * served (e.g. a multi-site instance, or a request that reached this site through a
     * different domain than the page's own). Overwriting that domain with the request's
     * would silently fetch the wrong host. Mutation-tested: dropping the "getHost() === ''"
     * guard (unconditionally rebuilding scheme/host/port) makes this test fail while
     * resolvesAHostLessPageUriToAnAbsoluteOneBeforeFetchingIt() above stays green, since
     * its fixture is already host-less either way.
     */
    #[Test]
    public function keepsAnAlreadyAbsolutePageUriUnchanged(): void
    {
        $routerStub = self::createStub(RouterInterface::class);
        $routerStub
            ->method('generateUri')
            ->willReturn(new Uri('https://other-domain.example/some-page?type=1716283827&_language=0'));

        $subject = $this->createSubject(
            $routerStub,
            'https://other-domain.example/some-page?type=1716283827&_language=0',
        );

        $serverRequest = new ServerRequest('https://example.com:8443/newsletter?pageId=42');

        self::assertSame(
            'rendered container',
            $subject->renderNewsletterPreviewPage(
                $serverRequest,
                42,
            ),
        );
    }

    /**
     * Builds the service under test with SiteFinder/Router wired to $routerStub, and
     * RequestFactory stubbed to assert the fetched URL is exactly $expectedFetchedUrl
     * (the actual regression check both tests above rely on).
     */
    private function createSubject(RouterInterface $routerStub, string $expectedFetchedUrl): NewsletterRenderService
    {
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
            ->willReturnCallback(
                static function (string $url) use ($expectedFetchedUrl): Response {
                    self::assertSame(
                        $expectedFetchedUrl,
                        $url,
                    );

                    $body = new Stream(
                        'php://temp',
                        'rw',
                    );
                    $body->write('newsletter content');
                    $body->rewind();

                    return new Response(
                        $body,
                        200,
                    );
                },
            );

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
            ->willReturn($viewStub);

        $configurationStub = self::createStub(Configuration::class);
        $configurationStub
            ->method('getTypoScriptSetting')
            ->willReturnCallback(
                static fn (string $path): ?array => $path === 'view/templateRootPaths' ? [] : null,
            );

        return new NewsletterRenderService(
            $requestFactoryStub,
            $siteFinderStub,
            $viewFactoryStub,
            $configurationStub,
        );
    }
}
