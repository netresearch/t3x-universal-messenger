<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 * SPDX-License-Identifier: LicenseRef-Netresearch-Restricted-Use
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
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(InlineCssMiddleware::class)]
final class InlineCssMiddlewareTest extends UnitTestCase
{
    /**
     * The first CSS file colours the newsletter text red.
     */
    private const FIRST_CSS_FILE = __DIR__ . '/Fixtures/first.css';

    /**
     * The second CSS file colours the newsletter text blue.
     */
    private const SECOND_CSS_FILE = __DIR__ . '/Fixtures/second.css';

    private const SECOND_CSS_RULE = 'color: blue';

    #[Test]
    public function laterConfiguredCssFileTakesPrecedenceOverAnEarlierOneWithEqualSpecificity(): void
    {
        $renderedHtml = $this->processWithInlineCssFiles(
            [
                '10' => self::FIRST_CSS_FILE,
                '20' => self::SECOND_CSS_FILE,
            ],
        );

        self::assertStringContainsString(self::SECOND_CSS_RULE, $renderedHtml);
        self::assertStringNotContainsString('color: red', $renderedHtml);
    }

    /**
     * An entry of "inlineCssFiles" that is no file name, e.g. a nested list,
     * is skipped; the other entries are still inlined.
     */
    #[Test]
    public function skipsAnInlineCssFilesEntryThatIsNoFileName(): void
    {
        $renderedHtml = $this->processWithInlineCssFiles(
            [
                '10' => [self::FIRST_CSS_FILE],
                '20' => self::SECOND_CSS_FILE,
            ],
        );

        self::assertStringContainsString(self::SECOND_CSS_RULE, $renderedHtml);
        self::assertStringNotContainsString('color: red', $renderedHtml);
    }

    /**
     * An "inlineCssFiles" setting that is no list inlines no file.
     */
    #[Test]
    public function inlinesNoFileWhenInlineCssFilesIsNoList(): void
    {
        $renderedHtml = $this->processWithInlineCssFiles(self::SECOND_CSS_FILE);

        self::assertStringContainsString('<p>Text</p>', $renderedHtml);
        self::assertStringNotContainsString(self::SECOND_CSS_RULE, $renderedHtml);
    }

    /**
     * Runs the middleware for a newsletter preview request with the given
     * "inlineCssFiles" setting and returns the rendered HTML.
     */
    private function processWithInlineCssFiles(mixed $inlineCssFiles): string
    {
        $configuration = self::createStub(Configuration::class);
        $configuration
            ->method('getTypoScriptSetting')
            ->willReturn($inlineCssFiles);

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

        return (string) $response->getBody();
    }
}
