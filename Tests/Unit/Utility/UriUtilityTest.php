<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit\Utility;

use Netresearch\UniversalMessenger\Tests\Unit\TrustedServerRequestTrait;
use Netresearch\UniversalMessenger\Utility\UriUtility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests that UriUtility resolves a host-less URI (GH-171) against a request's own
 * scheme/host/port, and leaves an already-absolute URI untouched.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(UriUtility::class)]
final class UriUtilityTest extends UnitTestCase
{
    use TrustedServerRequestTrait;

    /**
     * A host-less URI (a relative-base site's router output, GH-171) is rebuilt from the request,
     * once the request's own Host header has been confirmed trusted (HTTP_HOST matches SERVER_NAME:
     * SERVER_PORT, the default trustedHostsPattern).
     */
    #[Test]
    public function rebuildsAHostLessUriFromTheGivenRequest(): void
    {
        $uri           = new Uri('/some-page?type=1716283827');
        $serverRequest = $this->createTrustedServerRequest('https://example.com:8443/newsletter?pageId=42');

        self::assertSame(
            'https://example.com:8443/some-page?type=1716283827',
            (string) UriUtility::resolveAbsoluteUri(
                $uri,
                $serverRequest,
            ),
        );
    }

    /**
     * A URI that already carries a host is returned unchanged, never overwritten by the request's own host.
     */
    #[Test]
    public function leavesAUriWithAHostUnchanged(): void
    {
        $uri           = new Uri('https://other-domain.example/some-page?type=1716283827');
        $serverRequest = $this->createTrustedServerRequest('https://example.com:8443/newsletter?pageId=42');

        self::assertSame(
            'https://other-domain.example/some-page?type=1716283827',
            (string) UriUtility::resolveAbsoluteUri(
                $uri,
                $serverRequest,
            ),
        );
    }

    /**
     * A host-less URI must stay host-less when the request's own Host header is not trusted
     * (HTTP_HOST does not match SERVER_NAME:SERVER_PORT), instead of being resolved against
     * attacker-controlled input (GH-174). This is the SSRF-hardening re-check, independent of
     * TYPO3 core's VerifyHostHeader middleware having already run on the way in. Without it,
     * this method would resolve the host-less URI straight from $serverRequest->getUri(),
     * which reflects the (here: forged) Host header verbatim.
     */
    #[Test]
    public function keepsAHostLessUriUnresolvedWhenTheRequestsHostIsNotTrusted(): void
    {
        $uri           = new Uri('/some-page?type=1716283827');
        $serverRequest = new ServerRequest(
            'https://attacker.example:9200/newsletter?pageId=42',
            null,
            'php://input',
            [],
            [
                'HTTP_HOST'   => 'attacker.example:9200',
                'SERVER_NAME' => 'example.com',
                'SERVER_PORT' => '8443',
                'HTTPS'       => 'on',
            ],
        );

        self::assertSame(
            '/some-page?type=1716283827',
            (string) UriUtility::resolveAbsoluteUri(
                $uri,
                $serverRequest,
            ),
        );
    }

    /**
     * A request whose server params carry none of HTTP_HOST/SERVER_NAME/SERVER_PORT (a
     * synthetic or otherwise incomplete ServerRequest, e.g. one built by a caller outside a
     * real HTTP request cycle) must be treated as untrusted, not crash. See
     * UriUtility::isRequestHostTrusted() for why the underlying TYPO3 core delegation can
     * throw here.
     */
    #[Test]
    public function keepsAHostLessUriUnresolvedWhenTheRequestHasNoServerParamsAtAll(): void
    {
        $uri           = new Uri('/some-page?type=1716283827');
        $serverRequest = new ServerRequest('https://example.com:8443/newsletter?pageId=42');

        self::assertSame(
            '/some-page?type=1716283827',
            (string) UriUtility::resolveAbsoluteUri(
                $uri,
                $serverRequest,
            ),
        );
    }
}
