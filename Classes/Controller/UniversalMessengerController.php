<?php

/**
 * This file is part of the package netresearch/nrc-universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrcUniversalMessenger\Controller;

use Netresearch\NrcUniversalMessenger\Configuration;
use Netresearch\NrcUniversalMessenger\Domain\Model\NewsletterChannel;
use Netresearch\NrcUniversalMessenger\Domain\Repository\NewsletterChannelRepository;
use Netresearch\NrcUniversalMessenger\Service\NewsletterRenderService;
use Netresearch\NrcUniversalMessenger\Service\UniversalMessengerService;
use Netresearch\Sdk\UniversalMessenger\RequestBuilder\EventFile\CreateRequestBuilder;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

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
     * @var SiteFinder
     */
    private SiteFinder $siteFinder;

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
        $moduleData  = $this->request->getAttribute('moduleData');
        $languageId  = (int) $moduleData->get('language');
        $contentPage = BackendUtility::getRecord('pages', $this->pageId);

        // Show button only at pages matching our page type.
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

        $previewUri = PreviewUriBuilder::create($this->pageId)
            ->withAdditionalQueryParameters([
                'type' => self::PREVIEW_TYPE_NUMBER,
                'tx_nrcuniversalmessenger_newsletterpreview' => [
                    'pageId' => $this->pageId,
                ],
            ])
            ->withLanguage($languageId)
            ->buildUri();

        $previewUrl = (string) $previewUri;

        if (($previewUri === null) || ($previewUrl === '')) {
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
DebuggerUtility::var_dump(__METHOD__);
//DebuggerUtility::var_dump($newsletterChannel);

        if (!($newsletterChannel instanceof NewsletterChannel)
            || !$this->request->hasArgument('send')
        ) {
            return $this->forwardFlashMessage('error.invalidRequest');
        }

        $contentPage         = BackendUtility::getRecord('pages', $this->pageId);
        $site                = $this->siteFinder->getSiteByPageId($this->pageId);
        $newsletterType      = strtoupper($this->request->getArgument('send'));
        $newsletterChannelId = $newsletterChannel->getChannelId();
        $newsletterContent   = $this->newsletterRenderService->renderNewsletterPage($this->pageId);

//        // Embed all images
//        if ($newsletterChannel->getEmbedImages() === 'all') {
//            $newsletterContent = $this->convertImagesToDataUri(
//                $newsletterContent,
//                (string) $site->getBase()
//            );
//        }

        if ($newsletterType === 'TEST') {
            $newsletterChannelId .= '_Test';
        } else {
            $newsletterChannelId .= '_Live';
DebuggerUtility::var_dump('LIVE');
exit;
        }

        /** @var CreateRequestBuilder $createRequestBuilder */
        $createRequestBuilder = GeneralUtility::makeInstance(CreateRequestBuilder::class);

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
            ->setHtmlBodyEncoding('utf-8')
            ->setHtmlBodyTracking(false, false)
            ->setHtmlBodyContent(true, $newsletterContent)
            ->addTag($newsletterChannel->getTitle())
            ->addTag($newsletterType)
            ->create();

DebuggerUtility::var_dump($eventRequest);
//exit;

        $result = $this->universalMessengerService
            ->api()
            ->eventFile()
            ->event($eventRequest);

DebuggerUtility::var_dump($result);

        return $this->moduleTemplate->renderResponse('Backend/UniversalMessenger');
    }
}
