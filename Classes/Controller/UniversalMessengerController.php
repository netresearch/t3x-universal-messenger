<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Controller;

use Netresearch\Sdk\UniversalMessenger\RequestBuilder\EventFile\CreateRequestBuilder;
use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Domain\Model\NewsletterChannel;
use Netresearch\UniversalMessenger\Domain\Repository\NewsletterChannelRepository;
use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use Netresearch\UniversalMessenger\Service\UniversalMessengerService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * UniversalMessengerController.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class UniversalMessengerController extends AbstractBaseController
{
    /**
     * @var string
     */
    private const NEWSLETTER_SEND_TYPE_TEST = 'TEST';

    /**
     * @var SiteFinder
     */
    private readonly SiteFinder $siteFinder;

    /**
     * UniversalMessengerController constructor.
     *
     * @param ModuleTemplateFactory       $moduleTemplateFactory
     * @param UniversalMessengerService   $universalMessengerService
     * @param NewsletterChannelRepository $newsletterChannelRepository
     * @param NewsletterRenderService     $newsletterRenderService
     * @param SiteFinder                  $siteFinder
     */
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        UniversalMessengerService $universalMessengerService,
        NewsletterChannelRepository $newsletterChannelRepository,
        NewsletterRenderService $newsletterRenderService,
        SiteFinder $siteFinder
    ) {
        parent::__construct(
            $moduleTemplateFactory,
            $universalMessengerService,
            $newsletterChannelRepository,
            $newsletterRenderService
        );

        $this->siteFinder = $siteFinder;
    }

    /**
     * The main entry point.
     *
     * @return ResponseInterface
     */
    public function indexAction(): ResponseInterface
    {
        $contentPage = BackendUtility::getRecord('pages', $this->pageId);

        // Check if the selected page matches our newsletter page type
        if (($contentPage === null)
            || ($contentPage['doktype'] !== Configuration::getNewsletterPageDokType())
        ) {
            return $this->forwardFlashMessage(
                'error.pageNotAllowed',
                ContextualFeedbackSeverity::INFO
            );
        }

        // Check if backend user is allowed to access this newsletter
        if (!GeneralUtility::inList(
            $this->getBackendUserAuthentication()->user['universal_messenger_channels'],
            $contentPage['universal_messenger_channel']
        )) {
            return $this->forwardFlashMessage('error.accessNotAllowed');
        }

        $moduleData = $this->request->getAttribute('moduleData');
        $languageId = (int) $moduleData->get('language');
        $previewUrl = $this->getPreviewUrl($this->pageId, $languageId);

        // Check if the created preview URL is valid
        if (!$this->isUrlValid($previewUrl)) {
            return $this->forwardFlashMessage('error.noSiteConfiguration');
        }

        $newsletterChannel = $this->newsletterChannelRepository
            ->findByUid($contentPage['universal_messenger_channel']);

        $this->view->assign('pageId', $this->pageId);
        $this->view->assign('pageTitle', $contentPage['title']);
        $this->view->assign('previewUrl', $previewUrl);
        $this->view->assign('newsletterChannel', $newsletterChannel);

        $this->moduleTemplate->assign('content', $this->view->render());

        return $this->moduleTemplate->renderResponse('Backend/UniversalMessenger');
    }

    /**
     * @param NewsletterChannel|null $newsletterChannel
     *
     * @return ResponseInterface
     */
    public function createAction(?NewsletterChannel $newsletterChannel): ResponseInterface
    {
        // Check if the submitted request is valid
        if (!($newsletterChannel instanceof NewsletterChannel)
            || !$this->request->hasArgument('send')
        ) {
            return $this->forwardFlashMessage('error.invalidRequest');
        }

        $testChannelSuffix = $this->settings['newsletter']['testChannelSuffix'];
        $liveChannelSuffix = $this->settings['newsletter']['liveChannelSuffix'];

        $contentPage         = BackendUtility::getRecord('pages', $this->pageId);
        $site                = $this->siteFinder->getSiteByPageId($this->pageId);
        $newsletterType      = strtoupper($this->request->getArgument('send'));
        $newsletterChannelId = $newsletterChannel->getChannelId();
        $newsletterContent   = $this->newsletterRenderService->renderNewsletterPage($this->pageId);

        if ($newsletterType === self::NEWSLETTER_SEND_TYPE_TEST) {
            $newsletterChannelId .= $testChannelSuffix;
        } else {
            $newsletterChannelId .= $liveChannelSuffix;
        }

        /** @var CreateRequestBuilder $createRequestBuilder */
        $createRequestBuilder = GeneralUtility::makeInstance(CreateRequestBuilder::class);

        // Create the event file request
        $eventRequest = $createRequestBuilder
            ->addChannel($newsletterChannelId)
            ->setEmailBaseAndDownloadUrl(
                (string) $site->getBase(),
                (string) $site->getBase()
            )
            ->setEmailBodyType(false, true)
            ->setEventDetails(
                $newsletterType . strtoupper(bin2hex(random_bytes(16))),
                null,
                $newsletterChannel->isSkipUsedId()
            )
            ->setEmailAdresses(
                $newsletterChannel->getSender(),
                $newsletterChannel->getReplyTo()
            )
            ->setEmailSubject($contentPage['title'])
            ->setHtmlBodyEmbedImages($newsletterChannel->getEmbedImages())
            ->setHtmlBodyEncoding('UTF-8')
            ->setHtmlBodyTracking(false, false)
            ->setHtmlBodyContent(true, $newsletterContent)
            ->addTag($newsletterChannel->getTitle())
            ->addTag($newsletterType)
            ->create();

        $this->universalMessengerService
            ->api()
            ->eventFile()
            ->event($eventRequest);

        return $this->forwardFlashMessage(
            'success.newsletterSend',
            ContextualFeedbackSeverity::OK
        );
    }

    /**
     * Returns the newsletter preview URL.
     *
     * @param int $pageId
     * @param int $languageId
     *
     * @return string
     */
    private function getPreviewUrl(int $pageId, int $languageId): string
    {
        $previewUri = PreviewUriBuilder::create($pageId)
            ->withAdditionalQueryParameters([
                'type'                                    => self::PREVIEW_TYPE_NUMBER,
                'tx_universalmessenger_newsletterpreview' => [
                    'pageId' => $pageId,
                ],
            ])
            ->withLanguage($languageId)
            ->buildUri();

        return (string) $previewUri;
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
}
