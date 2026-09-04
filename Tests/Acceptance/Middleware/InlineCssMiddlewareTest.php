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

        self::assertSame('<p>Hello</p>', (string) $response->getBody());
    }

    /** Only the newsletter preview page type may be rewritten; every other page type's response must pass through untouched, even with CSS files configured. */
    #[Test]
    public function leavesContentUnchangedForAnyOtherPageType(): void
    {
        $subject = new InlineCssMiddleware($this->createConfigurationStub([$this->fixturePath()]));

        $request = (new ServerRequest())->withAttribute(
            'routing',
            new PageArguments(1, (string) (Constants::NEWSLETTER_PREVIEW_TYPENUM + 1), []),
        );

        $response = $subject->process($request, $this->createRequestHandlerReturning('<p>Hello</p>'));

        self::assertSame('<p>Hello</p>', (string) $response->getBody());
    }

    private function fixturePath(): string
    {
        return Environment::getProjectPath() . '/Tests/Acceptance/Fixtures/InlineCssMiddleware/style.css';
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

    private function createPreviewRequest(): ServerRequestInterface
    {
        return (new ServerRequest())->withAttribute(
            'routing',
            new PageArguments(1, (string) Constants::NEWSLETTER_PREVIEW_TYPENUM, []),
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
