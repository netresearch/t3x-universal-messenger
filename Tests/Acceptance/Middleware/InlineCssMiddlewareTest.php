<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Acceptance\Middleware;

use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Constants;
use Netresearch\UniversalMessenger\Middleware\InlineCssMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Routing\PageArguments;

/**
 * Acceptance test for InlineCssMiddleware: exercises the middleware through
 * its real PSR-15 process() method, with a real CSS fixture file and the
 * real "pelago/emogrifier" library doing the actual inlining, so this
 * verifies the middleware's true output, not just that it delegates to
 * Emogrifier. Configuration is the middleware's only true boundary
 * collaborator (it reads TypoScript, unavailable without a full TSFE), so
 * it is the only stub.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(InlineCssMiddleware::class)]
final class InlineCssMiddlewareTest extends TestCase
{
    /** Real fixture, real Emogrifier: the "color: red" declaration must end up as an inline style attribute on the matching element. */
    #[Test]
    public function inlinesCssFromTheConfiguredFileOnTheNewsletterPreviewPageType(): void
    {
        $subject = new InlineCssMiddleware($this->createConfigurationStub([$this->fixturePath()]));

        $response = $subject->process(
            $this->createPreviewRequest(),
            $this->createRequestHandlerReturning('<p>Hello</p>'),
        );

        self::assertSame(
            '<!DOCTYPE html>' . "\n"
            . '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>'
            . '<body><p style="color: red;">Hello</p></body></html>' . "\n",
            (string) $response->getBody(),
        );
    }

    /** No configured CSS files is the documented early-return path: content must pass through byte-for-byte unchanged, not just "unbroken." */
    #[Test]
    public function leavesContentUnchangedWhenNoCssFilesAreConfigured(): void
    {
        $subject = new InlineCssMiddleware($this->createConfigurationStub(null));

        $response = $subject->process(
            $this->createPreviewRequest(),
            $this->createRequestHandlerReturning('<p>Hello</p>'),
        );

        self::assertSame(
            '<p>Hello</p>',
            (string) $response->getBody(),
        );
    }

    /** An explicitly configured but empty file list is a second, independently-checked way to reach the same early return as null (a realistic TypoScript misconfiguration, not just the unset case). */
    #[Test]
    public function leavesContentUnchangedWhenTheConfiguredCssFileListIsEmpty(): void
    {
        $subject = new InlineCssMiddleware($this->createConfigurationStub([]));

        $response = $subject->process(
            $this->createPreviewRequest(),
            $this->createRequestHandlerReturning('<p>Hello</p>'),
        );

        self::assertSame(
            '<p>Hello</p>',
            (string) $response->getBody(),
        );
    }

    /** An empty response body with CSS files configured must not throw: CssInliner::fromHtml('') rejects empty input, so the middleware's own empty-content guard is load-bearing, not defensive dead code. */
    #[Test]
    public function leavesAnEmptyBodyEmptyEvenWithCssFilesConfigured(): void
    {
        $subject = new InlineCssMiddleware($this->createConfigurationStub([$this->fixturePath()]));

        $response = $subject->process(
            $this->createPreviewRequest(),
            $this->createRequestHandlerReturning(''),
        );

        self::assertSame(
            '',
            (string) $response->getBody(),
        );
    }

    /** A configured CSS file that does not exist on disk must be silently skipped (file_exists() guards the read), not fail the whole request. */
    #[Test]
    public function silentlySkipsAConfiguredCssFileThatDoesNotExist(): void
    {
        $subject = new InlineCssMiddleware($this->createConfigurationStub([
            Environment::getProjectPath() . '/Tests/Acceptance/Fixtures/InlineCssMiddleware/does-not-exist.css',
        ]));

        $response = $subject->process(
            $this->createPreviewRequest(),
            $this->createRequestHandlerReturning('<p>Hello</p>'),
        );

        // No CSS was actually loaded, so inlining runs with empty CSS: the
        // content is normalized (wrapped into a full document) but carries
        // no inline styles, proving the missing file was skipped rather
        // than raising an error.
        self::assertStringNotContainsString(
            'style=',
            (string) $response->getBody(),
        );
        self::assertStringContainsString(
            '<p>Hello</p>',
            (string) $response->getBody(),
        );
    }

    /** Pins the two HtmlPruner post-processing steps the docblock claims are covered: removing display:none elements and stripping classes made redundant by inlining. The single-rule "color: red" fixture used above cannot distinguish either from a no-op. */
    #[Test]
    public function removesDisplayNoneElementsAndRedundantClassesAfterInlining(): void
    {
        $subject = new InlineCssMiddleware($this->createConfigurationStub([$this->pruningFixturePath()]));

        $response = $subject->process(
            $this->createPreviewRequest(),
            $this->createRequestHandlerReturning('<div class="hidden">Hidden</div><p class="foo">Visible</p>'),
        );

        self::assertSame(
            '<!DOCTYPE html>' . "\n"
            . '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>'
            . '<body><p style="color: blue;">Visible</p></body></html>' . "\n",
            (string) $response->getBody(),
        );
    }

    /** Only the newsletter preview page type may be rewritten; every other page type's response must pass through untouched, even with CSS files configured. */
    #[Test]
    public function leavesContentUnchangedForAnyOtherPageType(): void
    {
        $subject = new InlineCssMiddleware($this->createConfigurationStub([$this->fixturePath()]));

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
            $this->createRequestHandlerReturning('<p>Hello</p>'),
        );

        self::assertSame(
            '<p>Hello</p>',
            (string) $response->getBody(),
        );
    }

    /** A request without a "routing" attribute at all (e.g. outside the frontend routing pipeline) must not be touched, distinct from the wrong-page-type case above. */
    #[Test]
    public function leavesContentUnchangedWhenTheRoutingAttributeIsMissing(): void
    {
        $subject = new InlineCssMiddleware($this->createConfigurationStub([$this->fixturePath()]));

        $response = $subject->process(
            new ServerRequest(),
            $this->createRequestHandlerReturning('<p>Hello</p>'),
        );

        self::assertSame(
            '<p>Hello</p>',
            (string) $response->getBody(),
        );
    }

    /** Path to the fixture with a single "color: red" rule, used by the base inlining test. */
    private function fixturePath(): string
    {
        return Environment::getProjectPath() . '/Tests/Acceptance/Fixtures/InlineCssMiddleware/style.css';
    }

    /** Path to the fixture pinning the two HtmlPruner steps: a display:none rule and a class made redundant by inlining. */
    private function pruningFixturePath(): string
    {
        return Environment::getProjectPath() . '/Tests/Acceptance/Fixtures/InlineCssMiddleware/pruning.css';
    }

    /**
     * @param string[]|null $inlineCssFiles
     */
    private function createConfigurationStub(?array $inlineCssFiles): Configuration
    {
        $configuration = self::createStub(Configuration::class);
        $configuration
            ->method('getTypoScriptSetting')
            ->willReturn($inlineCssFiles);

        return $configuration;
    }

    /** Builds a request routed to the newsletter preview page type, the only page type the middleware acts on. */
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

    /** Stands in for "the rest of the middleware stack": returns a fixed body regardless of the incoming request. */
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
