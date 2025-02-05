<?php

/**
 * This file is part of the package netresearch/universal-messenger.
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
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class InlineCssMiddleware implements MiddlewareInterface
{
    /**
     * @var Configuration
     */
    private Configuration $configuration;

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

        $stream = $requestHandler->handle($serverRequest)->getBody();
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

        $files      = array_reverse($inlineCssFiles);
        $cssContent = '';

        foreach ($files as $path) {
            $file = GeneralUtility::getFileAbsFileName($path);

            if (file_exists($file)) {
                $cssContent .= file_get_contents($file);
            }
        }

        $content     = HtmlNormalizer::fromHtml($content)->render();
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
