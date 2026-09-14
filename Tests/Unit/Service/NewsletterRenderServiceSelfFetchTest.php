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
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The self-fetch performed by NewsletterRenderService::getContentFromUrl() (used both by the
 * unauthenticated frontend preview and the authenticated backend dispatch path) must not follow
 * HTTP redirects (GH-174). The fetched URL is built from the current request's own scheme/host/
 * port (see UriUtility::resolveAbsoluteUri()); a redirect-following self-fetch lets a
 * compromised or attacker-controlled first hop pivot the request to an entirely different,
 * attacker-chosen target, widening the SSRF surface beyond the initial host.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(NewsletterRenderService::class)]
final class NewsletterRenderServiceSelfFetchTest extends UnitTestCase
{
    use NewsletterContentResponseTrait;

    /**
     * The self-fetch must disable HTTP redirect following.
     */
    #[Test]
    public function doesNotFollowRedirectsWhenFetchingTheNewsletterContent(): void
    {
        $capturedOptions = null;

        $requestFactoryStub = self::createStub(RequestFactory::class);
        $requestFactoryStub
            ->method('request')
            ->willReturnCallback(
                function (string $url, string $method, array $options) use (&$capturedOptions): Response {
                    $capturedOptions = $options;

                    return $this->createNewsletterContentResponse();
                },
            );

        $configurationStub = self::createStub(Configuration::class);
        $configurationStub
            ->method('getTypoScriptSetting')
            ->willReturn(null);

        $subject = new NewsletterRenderService(
            $requestFactoryStub,
            self::createStub(SiteFinder::class),
            self::createStub(ViewFactoryInterface::class),
            $configurationStub,
        );

        $subject->renderNewsletterPage('https://example.com/newsletter-page');

        self::assertIsArray($capturedOptions);
        self::assertArrayHasKey(
            'allow_redirects',
            $capturedOptions,
        );
        self::assertFalse($capturedOptions['allow_redirects']);
    }
}
