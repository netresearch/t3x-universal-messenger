<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Middleware;

use Netresearch\UniversalMessenger\Constants;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Routing\PageArguments;

/**
 * Decodes curly braces middleware.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class DecodeCurlyBracesMiddleware implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $response = $handler->handle($request);

        if (!$this->isPreviewTypeNumSet($request)) {
            return $response;
        }

        // Extract the content
        $stream = $response->getBody();
        $stream->rewind();

        $content   = $stream->getContents();
        $newStream = (new StreamFactory())
            ->createStream($this->decodeCurlyBraces($content));

        return $response->withBody($newStream);
    }

    /**
     * The DOMDocument class used by "pelago/emogrifier" encodes curly braces in URLs
     * as they are unsafe according https://www.rfc-editor.org/rfc/rfc1738. But we need to pass
     * the HTML to the Universal Messenger, so placeholders (surrounded by curly braces) should
     * not be modified. So we need to decode the HTML entities back to curly braces.
     *
     * @param string $content
     *
     * @return string
     */
    private function decodeCurlyBraces(string $content): string
    {
        // Replaces %7B and %7D back to { and }
        return (string) preg_replace_callback(
            '/' . urlencode('{') . '.*' . urlencode('}') . '/',
            static fn (array $matches): string => urldecode($matches[0]),
            $content
        );
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return bool
     */
    private function isPreviewTypeNumSet(ServerRequestInterface $request): bool
    {
        $pageArguments = $request->getAttribute('routing');

        if ($pageArguments instanceof PageArguments) {
            return ((int) $pageArguments->getPageType()) === Constants::NEWSLETTER_PREVIEW_TYPENUM;
        }

        return false;
    }
}
