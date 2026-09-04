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
use Netresearch\UniversalMessenger\Middleware\DecodeCurlyBracesMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Routing\PageArguments;

/**
 * Acceptance test for DecodeCurlyBracesMiddleware: exercises the middleware
 * through its real PSR-15 process() method with real request/response
 * objects, not a mocked collaborator standing in for the middleware itself.
 *
 * The middleware has no boundary collaborators to mock (unlike
 * InlineCssMiddleware, which needs Configuration), so this test doubles as
 * the file/config-parsing style Acceptance tests this tier is meant for:
 * real objects end to end, only the request handler's own response is a
 * stand-in for "the rest of the middleware stack."
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(DecodeCurlyBracesMiddleware::class)]
final class DecodeCurlyBracesMiddlewareTest extends TestCase
{
    /** Emogrifier's DOMDocument handling percent-encodes curly braces in URLs (RFC 1738); this middleware must decode them back so UM placeholders survive. */
    #[Test]
    public function decodesCurlyBracesOnTheNewsletterPreviewPageType(): void
    {
        $subject = new DecodeCurlyBracesMiddleware();

        $request = $this->createPreviewRequest();

        $response = $subject->process(
            $request,
            $this->createRequestHandlerReturning('<a href="https://example.org/?token=%7Bfirstname%7D">Link</a>'),
        );

        self::assertSame(
            '<a href="https://example.org/?token={firstname}">Link</a>',
            (string) $response->getBody(),
        );
    }

    /** The positive control: content without any encoded curly braces must pass through unchanged, proving the middleware does not corrupt ordinary content. */
    #[Test]
    public function leavesContentWithoutEncodedBracesUnchanged(): void
    {
        $subject = new DecodeCurlyBracesMiddleware();

        $response = $subject->process(
            $this->createPreviewRequest(),
            $this->createRequestHandlerReturning('<p>No placeholders here.</p>'),
        );

        self::assertSame(
            '<p>No placeholders here.</p>',
            (string) $response->getBody(),
        );
    }

    /** A request without a "routing" attribute at all (e.g. outside the frontend routing pipeline) must not be touched. */
    #[Test]
    public function leavesContentUnchangedWhenTheRoutingAttributeIsMissing(): void
    {
        $subject = new DecodeCurlyBracesMiddleware();

        $response = $subject->process(
            new ServerRequest(),
            $this->createRequestHandlerReturning('<a href="?x=%7Bfoo%7D">Link</a>'),
        );

        self::assertSame(
            '<a href="?x=%7Bfoo%7D">Link</a>',
            (string) $response->getBody(),
        );
    }

    /** Only the newsletter preview page type may be rewritten; every other page type's response must pass through untouched. */
    #[Test]
    public function leavesContentUnchangedForAnyOtherPageType(): void
    {
        $subject = new DecodeCurlyBracesMiddleware();

        $request = (new ServerRequest())->withAttribute(
            'routing',
            new PageArguments(
                1,
                (string) (Constants::NEWSLETTER_PREVIEW_TYPENUM + 1),
                [],
            ),
        );

        $response = $subject->process(
            $request,
            $this->createRequestHandlerReturning('<a href="?x=%7Bfoo%7D">Link</a>'),
        );

        self::assertSame(
            '<a href="?x=%7Bfoo%7D">Link</a>',
            (string) $response->getBody(),
        );
    }

    private function createPreviewRequest(): ServerRequestInterface
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

    private function createRequestHandlerReturning(string $body): RequestHandlerInterface
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
