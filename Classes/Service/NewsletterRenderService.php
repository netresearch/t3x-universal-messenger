<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Service;

use Netresearch\UniversalMessenger\Configuration;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * NewsletterRenderService.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class NewsletterRenderService implements SingletonInterface
{
    /**
     * @var int
     */
    public const VIEW_TYPE_NUMBER = 1716283827;

    /**
     * @var RequestFactory
     */
    private readonly RequestFactory $requestFactory;

    /**
     * @var SiteFinder
     */
    private readonly SiteFinder $siteFinder;

    /**
     * @var ViewFactoryInterface
     */
    private ViewFactoryInterface $viewFactory;

    /**
     * @var Configuration
     */
    protected Configuration $configuration;

    /**
     * Constructor.
     *
     * @param RequestFactory       $requestFactory
     * @param SiteFinder           $siteFinder
     * @param ViewFactoryInterface $viewFactory
     * @param Configuration        $configuration
     */
    public function __construct(
        RequestFactory $requestFactory,
        SiteFinder $siteFinder,
        ViewFactoryInterface $viewFactory,
        Configuration $configuration,
    ) {
        $this->requestFactory = $requestFactory;
        $this->siteFinder     = $siteFinder;
        $this->viewFactory    = $viewFactory;
        $this->configuration  = $configuration;
    }

    /**
     * Returns the URI to the newsletter page to render.
     *
     * @param int                $pageId
     * @param array<string, int> $arguments
     *
     * @return UriInterface|null
     */
    private function generatePageUri(int $pageId, array $arguments = []): ?UriInterface
    {
        try {
            return $this
                ->getSiteByPageId($pageId)
                ->getRouter()
                ->generateUri(
                    $pageId,
                    $arguments
                );
        } catch (SiteNotFoundException) {
            return null;
        }
    }

    /**
     * Get a site from current page identifier. Works only in frontend context (so not when in CLI and BACKEND context).
     *
     * @param int $pageId
     *
     * @return Site
     *
     * @throws SiteNotFoundException
     */
    private function getSiteByPageId(int $pageId): Site
    {
        return $this->siteFinder->getSiteByPageId($pageId);
    }

    /**
     * Renders the newsletter and returns the generated HTML.
     *
     * @param ServerRequestInterface $serverRequest
     * @param int                    $pageId
     *
     * @return string
     */
    public function renderNewsletterPreviewPage(ServerRequestInterface $serverRequest, int $pageId): string
    {
        $language   = $serverRequest->getAttribute('language');
        $languageId = $language instanceof SiteLanguage ? $language->getLanguageId() : 0;

        $content = $this->renderNewsletterContainer(
            $serverRequest,
            $this->renderByPageId($serverRequest, $pageId, $languageId)
        );

        return $this->clearUpContent($content);
    }

    /**
     * Renders the newsletter, ready to send using UM.
     *
     * @param string $url
     *
     * @return string
     */
    public function renderNewsletterPage(string $url): string
    {
        $content = $this->getContentFromUrl($url);

        return $this->clearUpContent($content);
    }

    /**
     * Cleans up to content. Removes redundant whitespaces and tabs.
     *
     * @param string $content
     *
     * @return string
     */
    private function clearUpContent(string $content): string
    {
        // Replace tab with space
        $content = (string) preg_replace('/\t/', ' ', trim($content));

        // Removes redundant spaces between HTML tags
        $content = (string) preg_replace('/>\s+</', '><', $content);

        // Removes redundant spaces between HTML tags
        return trim($content);
    }

    /**
     * @param ServerRequestInterface $serverRequest
     *
     * @return ViewInterface
     */
    private function getView(ServerRequestInterface $serverRequest): ViewInterface
    {
        $viewFactoryData = new ViewFactoryData(
            layoutRootPaths        : $this->configuration->getTypoScriptSetting('view/layoutRootPaths'),
            templateRootPaths      : $this->configuration->getTypoScriptSetting('view/templateRootPaths'),
            partialRootPaths       : $this->configuration->getTypoScriptSetting('view/partialRootPaths'),
            templatePathAndFilename: $this->configuration->getTypoScriptSetting('view/templatePathAndFilename'),
            request                : $serverRequest,
        );

        return $this->viewFactory->create($viewFactoryData);
    }

    /**
     * @param ServerRequestInterface $serverRequest
     * @param string                 $content
     *
     * @return string
     */
    private function renderNewsletterContainer(ServerRequestInterface $serverRequest, string $content): string
    {
        /** @var TypoScriptFrontendController $typoScriptFrontendController */
        $typoScriptFrontendController = $serverRequest->getAttribute('frontend.controller');

        // Pass the content as "content" variable to the container template, otherwise
        // use the "f:cObject" view helper to render the different template columns of
        // the selected backend page layout.
        return $this->getView($serverRequest)
            ->assign('content', $content)
            ->assign('settings', $this->configuration->getTypoScriptSetting('settings'))
            ->assign('data', $typoScriptFrontendController->page)
            ->render();
    }

    /**
     * Renders the page with the given page ID.
     *
     * @param ServerRequestInterface $serverRequest
     * @param int                    $pageId        The page UID
     * @param int                    $languageId    The language UID of the page
     *
     * @return string
     */
    private function renderByPageId(ServerRequestInterface $serverRequest, int $pageId, int $languageId): string
    {
        $url = (string) $this->generatePageUri(
            $pageId,
            [
                'type'      => self::VIEW_TYPE_NUMBER,
                '_language' => $languageId,
            ]
        );

        if (!$this->isUrlValid($url)) {
            throw new RuntimeException('Preview URL is invalid: ' . $url);
        }

        return $this->renderFluidView(
            $serverRequest,
            $this->getContentFromUrl($url)
        );
    }

    /**
     * Checks if the URL is valid or not.
     *
     * @param string $value
     *
     * @return bool
     */
    private function isUrlValid(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * @param ServerRequestInterface $serverRequest
     * @param string                 $templateSource
     *
     * @return string
     */
    private function renderFluidView(ServerRequestInterface $serverRequest, string $templateSource): string
    {
        //        if ($templateSource !== '') {
        //            $viewFactoryData = new ViewFactoryData(
        //                layoutRootPaths  : $this->configuration->getTypoScriptSetting('view/layoutRootPaths'),
        //                templateRootPaths: $this->configuration->getTypoScriptSetting('view/templateRootPaths'),
        //                partialRootPaths : $this->configuration->getTypoScriptSetting('view/partialRootPaths'),
        //                request          : $serverRequest,
        //            );
        //
        //            /** @var FluidViewAdapter $viewAdapter */
        //            $viewAdapter = $this->viewFactory
        //                ->create($viewFactoryData);
        //
        //            $renderingContext = $viewAdapter
        //                ->getRenderingContext();
        //
        // //            $renderingContext->setControllerName('NewsletterPreview');
        // //            $renderingContext->setControllerAction('Preview');
        //            $renderingContext->getTemplatePaths()
        //                ->setTemplateSource($templateSource);
        //
        //            return $viewAdapter
        //                ->render();
        //        }

        return $templateSource;
    }

    /**
     * Performs a GET-request and returns the content from the called URL.
     *
     * @param string $url
     *
     * @return string
     *
     * @throws RuntimeException
     */
    private function getContentFromUrl(string $url): string
    {
        $response = $this->requestFactory->request(
            $url,
            'GET',
            [
                'allow_redirects' => true,
                'headers'         => [
                    'Cache-Control' => 'no-cache',
                    'User-Agent'    => 'TYPO3',
                ],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Failed to load: ' . $url);
        }

        return $response
            ->getBody()
            ->getContents();
    }
}
