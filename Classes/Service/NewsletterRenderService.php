<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Service;

use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Utility\UriUtility;
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

/**
 * NewsletterRenderService.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
class NewsletterRenderService implements SingletonInterface
{
    /**
     * @var int
     */
    public const VIEW_TYPE_NUMBER = 1716283827;

    /**
     * @var string
     */
    private const TEMPLATE_PATH_AND_FILENAME_SETTING = 'view/templatePathAndFilename';

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
    private readonly ViewFactoryInterface $viewFactory;

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
                    $arguments,
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
            $this->renderByPageId(
                $serverRequest,
                $pageId,
                $languageId,
            ),
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
     * Whether the container template is configured, i.e. whether "view/templatePathAndFilename"
     * resolves to a real value in the TypoScript getView() also reads its paths from.
     *
     * This method's only real caller, indexAction(), runs in backend context, so it reads
     * module.tx_universalmessenger.* rather than plugin.tx_universalmessenger.* (TYPO3's
     * BackendConfigurationManager cannot read plugin.* at all in that context). The opt-in
     * "Example Newsletter Template" static template keeps both branches in sync via a
     * TypoScript copy directive. An integrator overriding
     * plugin.tx_universalmessenger.view.templatePathAndFilename without mirroring it on the
     * module.* branch would make this check and the real frontend preview disagree.
     *
     * @return bool
     */
    public function isNewsletterContainerTemplateConfigured(): bool
    {
        $templatePathAndFilename = $this->configuration->getTypoScriptSetting(self::TEMPLATE_PATH_AND_FILENAME_SETTING);

        return ($templatePathAndFilename !== null) && ($templatePathAndFilename !== '');
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
            templateRootPaths      : $this->configuration->getTypoScriptSetting('view/templateRootPaths'),
            partialRootPaths       : $this->configuration->getTypoScriptSetting('view/partialRootPaths'),
            layoutRootPaths        : $this->configuration->getTypoScriptSetting('view/layoutRootPaths'),
            templatePathAndFilename: $this->configuration->getTypoScriptSetting(self::TEMPLATE_PATH_AND_FILENAME_SETTING),
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
        $pageRecord = $serverRequest
            ->getAttribute('frontend.page.information')
            ?->getPageRecord() ?? [];

        // Pass the content as "content" variable to the container template, otherwise
        // use the "f:cObject" view helper to render the different template columns of
        // the selected backend page layout.
        return $this->getView($serverRequest)
            ->assign('content', $content)
            ->assign('settings', $this->configuration->getTypoScriptSetting('settings'))
            ->assign('data', $pageRecord)
            ->render();
    }

    /**
     * Renders the page with the given page ID.
     *
     * @param ServerRequestInterface $serverRequest The current frontend request; see
     *                                              UriUtility::resolveAbsoluteUri() for why
     *                                              it is needed here
     * @param int                    $pageId        The page UID
     * @param int                    $languageId    The language UID of the page
     *
     * @return string
     */
    private function renderByPageId(ServerRequestInterface $serverRequest, int $pageId, int $languageId): string
    {
        $pageUri = $this->generatePageUri(
            $pageId,
            [
                'type'      => self::VIEW_TYPE_NUMBER,
                '_language' => $languageId,
            ],
        );

        // $pageUri is null only when generatePageUri() caught a SiteNotFoundException; that
        // case falls through unchanged to isUrlValid()'s rejection below, same as before this
        // fallback was added.
        if ($pageUri instanceof UriInterface) {
            $pageUri = UriUtility::resolveAbsoluteUri(
                $pageUri,
                $serverRequest,
            );
        }

        $url = (string) $pageUri;

        if (!$this->isUrlValid($url)) {
            throw new RuntimeException('Preview URL is invalid: ' . $url);
        }

        return $this->getContentFromUrl($url);
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
     * Performs a GET-request and returns the content from the called URL.
     *
     * The fetched URL always targets this same TYPO3 instance (see UriUtility::
     * resolveAbsoluteUri() and UniversalMessengerController::getNewsletterUrl()), so there is
     * no legitimate reason for it to redirect elsewhere. Following redirects here would let a
     * compromised or attacker-controlled first hop (GH-174) pivot this self-fetch to an
     * entirely different, attacker-chosen target.
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
                'allow_redirects' => false,
                'headers'         => [
                    'Cache-Control' => 'no-cache',
                    'User-Agent'    => 'TYPO3',
                ],
            ],
        );

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Failed to load: ' . $url);
        }

        return $response
            ->getBody()
            ->getContents();
    }
}
