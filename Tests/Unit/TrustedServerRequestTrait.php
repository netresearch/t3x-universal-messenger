<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit;

use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * TrustedServerRequestTrait.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
trait TrustedServerRequestTrait
{
    /**
     * Builds a ServerRequest whose HTTP_HOST/SERVER_NAME/SERVER_PORT server params match the
     * given URI, so it passes UriUtility::resolveAbsoluteUri()'s trustedHostsPattern re-check
     * (GH-174) under the default ('SERVER_NAME') pattern, mirroring a request whose Host header
     * matches its own webserver-derived server params, as a real, already middleware-validated
     * request's would.
     *
     * @param string $uri
     *
     * @return ServerRequest
     */
    private function createTrustedServerRequest(string $uri): ServerRequest
    {
        $parts = parse_url($uri);
        self::assertIsArray($parts);
        self::assertArrayHasKey(
            'host',
            $parts,
        );

        $host = $parts['host'];
        $port = isset($parts['port']) ? (string) $parts['port'] : '443';

        return new ServerRequest(
            $uri,
            null,
            'php://input',
            [],
            [
                'HTTP_HOST'   => $host . ':' . $port,
                'SERVER_NAME' => $host,
                'SERVER_PORT' => $port,
                'HTTPS'       => ($parts['scheme'] ?? '') === 'https' ? 'on' : '',
            ],
        );
    }
}
