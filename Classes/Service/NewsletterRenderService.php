<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Service;

use Pelago\Emogrifier\CssInliner;
use Pelago\Emogrifier\HtmlProcessor\CssToAttributeConverter;
use Pelago\Emogrifier\HtmlProcessor\HtmlPruner;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Symfony\Component\CssSelector\Exception\ParseException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

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
    private const VIEW_TYPE_NUMBER = 1_716_283_827;

    /**
     * @var ConfigurationManagerInterface
     */
    private readonly ConfigurationManagerInterface $configurationManager;

    /**
     * @var RequestFactory
     */
    private readonly RequestFactory $requestFactory;

    /**
     * @var SiteFinder
     */
    private readonly SiteFinder $siteFinder;

    /**
     * Constructor.
     *
     * @param ConfigurationManagerInterface $configurationManager
     * @param RequestFactory                $requestFactory
     * @param SiteFinder                    $siteFinder
     */
    public function __construct(
        ConfigurationManagerInterface $configurationManager,
        RequestFactory $requestFactory,
        SiteFinder $siteFinder
    ) {
        $this->configurationManager = $configurationManager;
        $this->requestFactory       = $requestFactory;
        $this->siteFinder           = $siteFinder;
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
     * @return StandaloneView
     */
    private function getStandaloneView(): StandaloneView
    {
        return GeneralUtility::makeInstance(StandaloneView::class);
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
     * @param ServerRequestInterface $request
     * @param int                    $pageId
     *
     * @return string
     *
     * @throws ParseException
     * @throws RuntimeException
     */
    public function renderNewsletterPreviewPage(ServerRequestInterface $request, int $pageId): string
    {
        $content = $this->addInlineCss(
            $this->renderNewsletterContainer(
                $request,
                $this->renderByPageId($pageId)
            )
        );

        return $this->clearUpContent($content);
    }

    /**
     * Renders the newsletter, ready to send using UM.
     *
     * @param string $url
     *
     * @return string
     *
     * @throws ParseException
     * @throws RuntimeException
     */
    public function renderNewsletterPage(string $url): string
    {
        $content = $this->addInlineCss($this->getContentFromUrl($url));

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
        $content = preg_replace('/\t/', ' ', trim($content));

        // Removes redundant spaces between HTML tags
        return trim(preg_replace('/>\s+</', '><', $content));
    }

    /**
     * @param ServerRequestInterface $request
     * @param string                 $content
     *
     * @return string
     */
    private function renderNewsletterContainer(ServerRequestInterface $request, string $content): string
    {
        $configuration = $this->getExtensionSettings();

        $standaloneView = $this->getStandaloneView();
        $standaloneView->setRequest($request);
        $standaloneView->setLayoutRootPaths($configuration['view']['layoutRootPaths']);
        $standaloneView->setPartialRootPaths($configuration['view']['partialRootPaths']);
        $standaloneView->setTemplateRootPaths($configuration['view']['templateRootPaths']);

        if (isset($configuration['view']['templatePathAndFilename'])) {
            $standaloneView->setTemplatePathAndFilename($configuration['view']['templatePathAndFilename']);
        }

        $standaloneView->assign('content', $content);

        return $standaloneView->render();
    }

    /**
     * Renders the page with the given page ID.
     *
     * @param int $pageId
     *
     * @return string
     *
     * @throws RuntimeException
     */
    private function renderByPageId(int $pageId): string
    {
        $url = (string) $this->generatePageUri(
            $pageId,
            [
                'type' => self::VIEW_TYPE_NUMBER,
            ]
        );

        if (!$this->isUrlValid($url)) {
            throw new RuntimeException('Preview URL is invalid: ' . $url);
        }

        return $this->renderFluidView(
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
     * @param string $templateSource
     *
     * @return string
     */
    private function renderFluidView(string $templateSource): string
    {
        if ($templateSource !== '') {
            $configuration = $this->getExtensionSettings();

            $standaloneView = $this->getStandaloneView();
            $standaloneView->setLayoutRootPaths($configuration['view']['layoutRootPaths']);
            $standaloneView->setPartialRootPaths($configuration['view']['partialRootPaths']);
            $standaloneView->setTemplateRootPaths($configuration['view']['templateRootPaths']);
            $standaloneView->setTemplateSource($templateSource);

            return $standaloneView->render();
        }

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

        if (isset($configuration['settings']['inlineCssFiles'])
            && ($configuration['settings']['inlineCssFiles'] !== [])
        ) {
            $files      = array_reverse($configuration['settings']['inlineCssFiles']);
            $cssContent = '';

            foreach ($files as $path) {
                $file = GeneralUtility::getFileAbsFileName($path);

                if (file_exists($file)) {
                    $cssContent .= file_get_contents($file);
                }
            }

            $cssInliner  = CssInliner::fromHtml($content)->inlineCss($cssContent);
            $domDocument = $cssInliner->getDomDocument();

            HtmlPruner::fromDomDocument($domDocument)
                ->removeElementsWithDisplayNone()
                ->removeRedundantClassesAfterCssInlined($cssInliner);

            $content = CssToAttributeConverter::fromDomDocument($domDocument)
                ->convertCssToVisualAttributes()
                ->render();
        }

        return $content;
    }
}
