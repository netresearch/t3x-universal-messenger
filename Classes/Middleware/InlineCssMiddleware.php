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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

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
     * @var ConfigurationManagerInterface
     */
    private readonly ConfigurationManagerInterface $configurationManager;

    /**
     * Constructor.
     *
     * @param ConfigurationManagerInterface $configurationManager
     */
    public function __construct(
        ConfigurationManagerInterface $configurationManager,
    ) {
        $this->configurationManager = $configurationManager;
    }

    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     *
     * @throws ParseException
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $response = $handler->handle($request);

        if (!$this->isPreviewTypeNumSet($request)) {
            return $response;
        }

        $stream = $handler->handle($request)->getBody();
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
        $configuration = $this->getExtensionSettings();

        // Abort if no CSS files should be inlined
        if (!isset($configuration['settings']['inlineCssFiles'])
            || ($configuration['settings']['inlineCssFiles'] === [])
        ) {
            return $content;
        }

        $files      = array_reverse($configuration['settings']['inlineCssFiles']);
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
     * Returns the extensions typoscript configuration.
     *
     * @return array<string, array<string, string|string[]>>
     */
    private function getExtensionSettings(): array
    {
        return $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK
        );
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return bool
     */
    private function isPreviewTypeNumSet(ServerRequestInterface $request): bool
    {
        return ((int) $request->getAttribute('routing')->getPageType()) === Constants::NEWSLETTER_PREVIEW_TYPENUM;
    }
}
