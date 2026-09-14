<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Utility;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Throwable;
use TYPO3\CMS\Core\Middleware\VerifyHostHeader;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * UriUtility.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
final class UriUtility
{
    /**
     * Static-only class.
     */
    private function __construct() {}

    /**
     * Resolves a possibly host-less URI to an absolute one, using the given request's own
     * scheme, host and port as the fallback source. Returns the given URI unchanged when it
     * already carries a host.
     *
     * TYPO3's site "base" configuration accepts a relative value (base: /) as a documented
     * option for a single-domain site (TYPO3 core's own site-configuration field help text,
     * typo3/cms-backend/Resources/Private/Language/siteconfiguration_fieldinformation.xlf,
     * trans-unit "site.base": "...Can be https://www.example.com/ or just /, if it is just a
     * / you cannot rely on TYPO3 creating full URLs"). A URI generated through such a site's
     * router then has no host, which fails PHP's FILTER_VALIDATE_URL and any HTTP client
     * expecting an absolute target.
     *
     * The resolved URI is fed into a server-side HTTP self-fetch by the caller (see
     * NewsletterRenderService::getContentFromUrl()). Relying solely on the inbound
     * VerifyHostHeader middleware having already validated the request's Host header is a
     * precondition held by convention, not enforced here (GH-174). This method therefore
     * re-validates the request's own Host header against the configured trustedHostsPattern
     * before using it as the fallback host, independently of that middleware, so a future
     * routing/middleware-order change cannot silently reopen a host-header-trusted self-fetch.
     * A host that fails this check is not filled in, leaving $uri host-less so the caller's
     * existing URL validation rejects it, same as before this fallback existed.
     *
     * @param UriInterface           $uri           The URI to resolve, possibly host-less
     * @param ServerRequestInterface $serverRequest The current request, used as the host/
     *                                              scheme/port source when $uri itself
     *                                              carries none
     *
     * @return UriInterface
     */
    public static function resolveAbsoluteUri(UriInterface $uri, ServerRequestInterface $serverRequest): UriInterface
    {
        if ($uri->getHost() !== '') {
            return $uri;
        }

        if (!self::isRequestHostTrusted($serverRequest)) {
            return $uri;
        }

        $requestUri = $serverRequest->getUri();

        return $uri
            ->withScheme($requestUri->getScheme())
            ->withHost($requestUri->getHost())
            ->withPort($requestUri->getPort());
    }

    /**
     * Re-validates the given request's Host header against TYPO3's configured
     * trustedHostsPattern ($GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern']), using
     * the same algorithm as TYPO3\CMS\Core\Middleware\VerifyHostHeader (the middleware that
     * normally validates it on the way in) rather than a separate reimplementation, so this
     * stays in lockstep with core's own trust decision instead of silently drifting from it
     * on a future TYPO3 update.
     *
     * @param ServerRequestInterface $serverRequest
     *
     * @return bool
     */
    private static function isRequestHostTrusted(ServerRequestInterface $serverRequest): bool
    {
        $trustedHostsPattern = $GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] ?? '';

        try {
            $verifyHostHeader = GeneralUtility::makeInstance(
                VerifyHostHeader::class,
                $trustedHostsPattern,
            );

            $serverParams = $serverRequest->getServerParams();
            $httpHost     = (string) ($serverParams['HTTP_HOST'] ?? '');

            return $verifyHostHeader->isAllowedHostHeaderValue(
                $httpHost,
                $serverParams,
            );
        } catch (Throwable) {
            // VerifyHostHeader is marked as internal (no BC promise) and may also be XCLASSed
            // with an incompatible constructor, so makeInstance() itself can throw, not just
            // the delegated call: as observed against TYPO3 v14's VerifyHostHeader::
            // hostHeaderValueMatchesTrustedHostsPattern() (typo3/cms-core Classes/Middleware/
            // VerifyHostHeader.php), it reads $serverParams['SERVER_NAME']/['SERVER_PORT']
            // with no null-coalescing, so a request whose server params don't carry those keys
            // (e.g. a synthetic ServerRequest built outside a real HTTP request cycle) can
            // throw there too. Catch broadly, since internal core code is not guaranteed to
            // keep surfacing exactly today's exception class, and fail closed: treat any such
            // failure as an untrusted host.
            return false;
        }
    }
}
