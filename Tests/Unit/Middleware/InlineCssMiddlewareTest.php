<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit\Middleware;

use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Constants;
use Netresearch\UniversalMessenger\Middleware\InlineCssMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests that multiple configured "inlineCssFiles" are applied in the documented
 * key order, i.e. a higher key can override a lower one with equal specificity.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(InlineCssMiddleware::class)]
final class InlineCssMiddlewareTest extends UnitTestCase
{
    #[Test]
    public function laterConfiguredCssFileTakesPrecedenceOverAnEarlierOneWithEqualSpecificity(): void
    {
        $configuration = self::createStub(Configuration::class);
        $configuration
            ->method('getTypoScriptSetting')
            ->willReturn(
                [
                    '10' => __DIR__ . '/Fixtures/first.css',
                    '20' => __DIR__ . '/Fixtures/second.css',
                ],
            );

        $request = (new ServerRequest())
            ->withAttribute(
                'routing',
                new PageArguments(1, (string) Constants::NEWSLETTER_PREVIEW_TYPENUM, []),
            );

        $requestHandler = self::createStub(RequestHandlerInterface::class);
        $requestHandler
            ->method('handle')
            ->willReturn(new HtmlResponse('<html><body><p class="newsletter-text">Text</p></body></html>'));

        $response = (new InlineCssMiddleware($configuration))
            ->process($request, $requestHandler);

        $renderedHtml = (string) $response->getBody();

        self::assertStringContainsString('color: blue', $renderedHtml);
        self::assertStringNotContainsString('color: red', $renderedHtml);
    }
}
