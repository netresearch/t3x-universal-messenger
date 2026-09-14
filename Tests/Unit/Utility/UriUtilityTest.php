<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit\Utility;

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
    /**
     * A host-less URI (a relative-base site's router output, GH-171) is rebuilt from the request.
     */
    #[Test]
    public function rebuildsAHostLessUriFromTheGivenRequest(): void
    {
        $uri           = new Uri('/some-page?type=1716283827');
        $serverRequest = new ServerRequest('https://example.com:8443/newsletter?pageId=42');

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
        $serverRequest = new ServerRequest('https://example.com:8443/newsletter?pageId=42');

        self::assertSame(
            'https://other-domain.example/some-page?type=1716283827',
            (string) UriUtility::resolveAbsoluteUri(
                $uri,
                $serverRequest,
            ),
        );
    }
}
