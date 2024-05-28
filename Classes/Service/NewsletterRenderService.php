<?php

/**
 * This file is part of the package netresearch/nrc-universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrcUniversalMessenger\Service;

use Pelago\Emogrifier\CssInliner;
use Pelago\Emogrifier\HtmlProcessor\CssToAttributeConverter;
use Pelago\Emogrifier\HtmlProcessor\HtmlPruner;
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
     * @return array<string, array<string, string[]>>
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
     * @param int                $pageId
     * @param array<string, int> $arguments
     *
     * @return string
     *
     * @throws SiteNotFoundException
     */
    private function generatePageUri(int $pageId, array $arguments = []): string
    {
        return (string) $this
            ->getSiteByPageId($pageId)
            ->getRouter()
            ->generateUri($pageId, $arguments);
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
     * @param int $pageId
     *
     * @return string
     *
     * @throws SiteNotFoundException
     */
    public function renderNewsletterPage(int $pageId): string
    {
        return $this->renderNewsletterContainer(
            $this->renderByPageId($pageId)
        );
    }

    /**
     * @param string $content
     *
     * @return string
     */
    private function renderNewsletterContainer(string $content): string
    {
        $configuration = $this->getExtensionSettings();

        $standaloneView = $this->getStandaloneView();
        $standaloneView->setLayoutRootPaths($configuration['view']['layoutRootPaths']);
        $standaloneView->setPartialRootPaths($configuration['view']['partialRootPaths']);
        $standaloneView->setTemplatePathAndFilename('EXT:nrc_universal_messenger/Resources/Private/Templates/NewsletterContainer.html');
        $standaloneView->assign('content', $content);

        return $this->addInlineCss(
            $standaloneView->render()
        );
    }

    /**
     * Renders the page with the given page ID.
     *
     * @param int $pageId
     *
     * @return string
     *
     * @throws SiteNotFoundException
     */
    private function renderByPageId(int $pageId): string
    {
        $uri = $this->generatePageUri(
            $pageId,
            [
                'type' => self::VIEW_TYPE_NUMBER,
            ]
        );

        $content = $this->getContentFromUri($uri);

        return $this->renderFluidView($content);
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
     * @param string $uri
     *
     * @return string
     *
     * @throws RuntimeException
     */
    private function getContentFromUri(string $uri): string
    {
        $response = $this->requestFactory->request(
            $uri,
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
            throw new RuntimeException('Failed to load: ' . $uri);
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

        if ($configuration['settings']['inlineCssFiles'] !== []) {
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
