<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Acceptance\Middleware;

use Netresearch\UniversalMessenger\Constants;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Routing\PageArguments;

/**
 * Shared request/handler builders for the frontend middleware Acceptance
 * tests: both InlineCssMiddleware and DecodeCurlyBracesMiddleware share the
 * same "newsletter preview page type" routing gate, so their tests need the
 * identical fixtures.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
abstract class AbstractMiddlewareAcceptanceTestCase extends TestCase
{
    /** Builds a request routed to the newsletter preview page type, the only page type the middlewares act on. */
    protected function createPreviewRequest(): ServerRequestInterface
    {
        return (new ServerRequest())->withAttribute(
            'routing',
            new PageArguments(
                1,
                (string) Constants::NEWSLETTER_PREVIEW_TYPENUM,
                [],
            ),
        );
    }

    /** Stands in for "the rest of the middleware stack": returns a fixed body regardless of the incoming request. */
    protected function createRequestHandlerReturning(string $body): RequestHandlerInterface
    {
        return new class ($body) implements RequestHandlerInterface {
            public function __construct(private readonly string $body) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new Response())->withBody(
                    (new StreamFactory())->createStream($this->body),
                );
            }
        };
    }
}
