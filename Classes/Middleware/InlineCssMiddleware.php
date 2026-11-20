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

namespace Netresearch\UniversalMessenger\Middleware;

use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Constants;
use Pelago\Emogrifier\CssInliner;
use Pelago\Emogrifier\HtmlProcessor\CssToAttributeConverter;
use Pelago\Emogrifier\HtmlProcessor\HtmlNormalizer;
use Pelago\Emogrifier\HtmlProcessor\HtmlPruner;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\CssSelector\Exception\ParseException;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Inline CSS middleware.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
class InlineCssMiddleware implements MiddlewareInterface
{
    /**
     * @var Configuration
     */
    private readonly Configuration $configuration;

    /**
     * Constructor.
     *
     * @param Configuration $configuration
     */
    public function __construct(
        Configuration $configuration,
    ) {
        $this->configuration = $configuration;
    }

    /**
     * @param ServerRequestInterface  $serverRequest
     * @param RequestHandlerInterface $requestHandler
     *
     * @return ResponseInterface
     *
     * @throws ParseException
     */
    public function process(
        ServerRequestInterface $serverRequest,
        RequestHandlerInterface $requestHandler,
    ): ResponseInterface {
        $response = $requestHandler->handle($serverRequest);

        if (!$this->isPreviewTypeNumSet($serverRequest)) {
            return $response;
        }

        // Extract the content
        $stream = $response->getBody();
        $stream->rewind();

        $content   = $stream->getContents();
        $newStream = (new StreamFactory())
            ->createStream($this->addInlineCss($content));

        return $response->withBody($newStream);
    }

    /**
     * Converts external CSS styles into inline style attributes to ensure proper display on email
     * and mobile device readers that lack stylesheet support.
     *
     * @param string $content
     *
     * @return string
     *
     * @throws ParseException
     */
    private function addInlineCss(string $content): string
    {
        $inlineCssFiles = $this->configuration->getTypoScriptSetting('settings/inlineCssFiles');

        // Abort if no CSS files should be inlined
        if (
            ($inlineCssFiles === null)
            || ($inlineCssFiles === [])
        ) {
            return $content;
        }

        // Abort if content is empty
        if ($content === '') {
            return $content;
        }

        $cssContent = '';

        // A setting that is no list of files ("inlineCssFiles = …") inlines
        // no file, and so does an entry that is no file name.
        foreach (is_array($inlineCssFiles) ? $inlineCssFiles : [] as $path) {
            if (!is_string($path)) {
                continue;
            }

            $file = GeneralUtility::getFileAbsFileName($path);

            if (file_exists($file)) {
                $cssContent .= file_get_contents($file);
            }
        }

        $content = HtmlNormalizer::fromHtml($this->wrapFragmentInHtmlElement($content))->render();

        // Ensure content is not empty after normalization
        if ($content === '') {
            return $content;
        }

        $cssInliner  = CssInliner::fromHtml($content)->inlineCss($cssContent);
        $domDocument = $cssInliner->getDomDocument();

        HtmlPruner::fromDomDocument($domDocument)
            ->removeElementsWithDisplayNone()
            ->removeRedundantClassesAfterCssInlined($cssInliner);

        return CssToAttributeConverter::fromDomDocument($domDocument)
            ->convertCssToVisualAttributes()
            ->render();
    }

    /**
     * Wraps markup that has no <html> element into one, keeping a leading DOCTYPE, and any comments
     * in front of it, outside the wrapper.
     *
     * Emogrifier puts its Content-Type meta tag in front of markup without an <html> element, so
     * it lands before the DOCTYPE. libxml2 2.15 then drops that DOCTYPE and writes an HTML 4.0
     * Transitional one instead. With an <html> element the meta tag goes inside it.
     *
     * @param non-empty-string $content
     *
     * @return non-empty-string
     */
    private function wrapFragmentInHtmlElement(string $content): string
    {
        if (preg_match('/<html[\s>]/i', $content) === 1) {
            return $content;
        }

        if (preg_match('/^(?:\s|<!--.*?-->)*<!DOCTYPE[^>]*>/is', $content, $matches) === 1) {
            return $matches[0] . '<html>' . substr($content, strlen($matches[0])) . '</html>';
        }

        return '<html>' . $content . '</html>';
    }

    /**
     * @param ServerRequestInterface $serverRequest
     *
     * @return bool
     */
    private function isPreviewTypeNumSet(ServerRequestInterface $serverRequest): bool
    {
        $pageArguments = $serverRequest->getAttribute('routing');

        if ($pageArguments instanceof PageArguments) {
            return ((int) $pageArguments->getPageType()) === Constants::NEWSLETTER_PREVIEW_TYPENUM;
        }

        return false;
    }
}
