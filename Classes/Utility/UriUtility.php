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

        $requestUri = $serverRequest->getUri();

        return $uri
            ->withScheme($requestUri->getScheme())
            ->withHost($requestUri->getHost())
            ->withPort($requestUri->getPort());
    }
}
