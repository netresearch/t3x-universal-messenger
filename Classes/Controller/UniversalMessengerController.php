<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Controller;

use Exception;
use Netresearch\Sdk\UniversalMessenger\Exception\ServiceException;
use Netresearch\Sdk\UniversalMessenger\Model\NewsletterStatus;
use Netresearch\Sdk\UniversalMessenger\RequestBuilder\EventFile\CreateRequestBuilder;
use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Domain\Model\NewsletterChannel;
use Netresearch\UniversalMessenger\Domain\Repository\NewsletterChannelRepository;
use Netresearch\UniversalMessenger\Domain\Repository\PageRepository;
use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use Netresearch\UniversalMessenger\Service\UniversalMessengerService;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\Buttons\ButtonInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;

use function in_array;

/**
 * UniversalMessengerController.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class UniversalMessengerController extends AbstractBaseController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var string
     */
    private const NEWSLETTER_SEND_TYPE_TEST = 'TEST';

    /**
     * @var string
     */
    private const NEWSLETTER_SEND_TYPE_LIVE = 'LIVE';

    /**
     * @var SiteFinder
     */
    private readonly SiteFinder $siteFinder;

    /**
     * UniversalMessengerController constructor.
     *
     * @param ModuleTemplateFactory       $moduleTemplateFactory
     * @param ExtensionConfiguration      $extensionConfiguration
     * @param UniversalMessengerService   $universalMessengerService
     * @param PageRepository              $pageRepository
     * @param NewsletterChannelRepository $newsletterChannelRepository
     * @param NewsletterRenderService     $newsletterRenderService
     * @param SiteFinder                  $siteFinder
     */
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        ExtensionConfiguration $extensionConfiguration,
        UniversalMessengerService $universalMessengerService,
        PageRepository $pageRepository,
        NewsletterChannelRepository $newsletterChannelRepository,
        NewsletterRenderService $newsletterRenderService,
        SiteFinder $siteFinder
    ) {
        parent::__construct(
            $moduleTemplateFactory,
            $extensionConfiguration,
            $universalMessengerService,
            $pageRepository,
            $newsletterChannelRepository,
            $newsletterRenderService
        );

        $this->siteFinder = $siteFinder;
    }

    /**
     * The main entry point.
     *
     * @return ResponseInterface
     *
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws RouteNotFoundException
     */
    public function indexAction(): ResponseInterface
    {
        // Create button bar
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();

        // Add language dropdown
        if (($languageButton = $this->makeLanguageSwitchButton($buttonBar)) instanceof ButtonInterface) {
            $buttonBar->addButton(
                $languageButton,
                ButtonBar::BUTTON_POSITION_LEFT,
                0
            );
        }

        $contentRecord = BackendUtility::getRecord('pages', $this->pageId);

        // Check if the selected page matches our newsletter page type
        if (($contentRecord === null)
            || ($contentRecord['doktype'] !== Configuration::getNewsletterPageDokType())
        ) {
            return $this->forwardFlashMessage(
                'error.pageNotAllowed',
                ContextualFeedbackSeverity::INFO
            );
        }

        // Check if the page is hidden
        if (!isset($contentRecord['hidden'])
            || ($contentRecord['hidden'] >= 1)
        ) {
            return $this->forwardFlashMessage('error.pageHidden');
        }

        // Check if the page has required newsletter channel configuration or just the default value
        if (!isset($contentRecord['universal_messenger_channel'])
            || ($contentRecord['universal_messenger_channel'] <= 0)
        ) {
            return $this->forwardFlashMessage('error.missingChannelConfiguration');
        }

        // Check if backend user is allowed to access this newsletter
        if (!in_array(
            $contentRecord['universal_messenger_channel'],
            $this->getNewsletterChannelPermissions(),
            true
        )) {
            return $this->forwardFlashMessage('error.accessNotAllowed');
        }

        $languageId    = (int) $this->moduleData->get('language');
        $newsletterUrl = $this->getNewsletterUrl($this->pageId, $languageId);

        // Check if the created preview URL is valid
        if (!$this->isUrlValid($newsletterUrl)) {
            return $this->forwardFlashMessage('error.noSiteConfiguration');
        }

        // Check if a newsletter status is available
        $newsletterEventId = $this->generateLiveEventId();
        $newsletterChannel = $this->newsletterChannelRepository
            ->findByUid($contentRecord['universal_messenger_channel']);

        $status = $this->getNewsletterStatus($newsletterEventId);

        // Show the status if available
        if (($status instanceof NewsletterStatus)
            && ($status->error === null)
        ) {
            if (($newsletterChannel instanceof NewsletterChannel)
                && $newsletterChannel->isSkipUsedId()
            ) {
                $this->view->assign('disableLiveButton', true);
            }

            $this->renderStatusMessage($status);
        }

        $this->view->assign('pageId', $this->pageId);
        $this->view->assign('pageTitle', $this->getPageTitle($contentRecord, $languageId));
        $this->view->assign('previewUrl', $newsletterUrl);
        $this->view->assign('newsletterChannel', $newsletterChannel);

        $this->moduleTemplate->assign('content', $this->view->render());

        return $this->moduleTemplate->renderResponse('Backend/UniversalMessenger');
    }

    /**
     * Returns the localized page title.
     *
     * @param array $pageRecord
     * @param int   $languageId
     *
     * @return string
     */
    private function getPageTitle(array $pageRecord, int $languageId): string
    {
        $pageTitle       = $pageRecord['title'];
        $localizedRecord = BackendUtility::getRecordLocalization('pages', $this->pageId, $languageId);

        if ($localizedRecord !== []) {
            return $localizedRecord[0]['title'];
        }

        return $pageTitle;
    }

    /**
     * Returns an array of newsletter channel permissions. The newsletter channel permissions from BE Groups
     * are also taken into consideration and are merged into User permissions.
     *
     * @return int[]
     */
    private function getNewsletterChannelPermissions(): array
    {
        $backendUserAuthentication = $this->getBackendUserAuthentication();
        $newsletterChannelIds      = '';

        // Newsletter channel permissions of the groups
        foreach ($backendUserAuthentication->userGroups as $group) {
            if (isset($group['universal_messenger_channels'])) {
                $newsletterChannelIds .= ',' . $group['universal_messenger_channels'];
            }
        }

        // Newsletter channel permissions of the user record
        if ($backendUserAuthentication->user['universal_messenger_channels']) {
            $newsletterChannelIds .= ',' . $backendUserAuthentication->user['universal_messenger_channels'];
        }

        // Make the IDs unique
        $newsletterChannelIds = GeneralUtility::intExplode(',', $newsletterChannelIds);

        // Remove empty values
        $newsletterChannelIds = array_filter($newsletterChannelIds);

        // Remove duplicate values
        $newsletterChannelIds = array_unique($newsletterChannelIds);

        return array_values($newsletterChannelIds);
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

        try {
            $languageId    = (int) $this->moduleData->get('language');
            $newsletterUrl = $this->getNewsletterUrl($this->pageId, $languageId, false);

            // Check if the created newsletter URL is valid
            if (!$this->isUrlValid($newsletterUrl)) {
                return $this->forwardFlashMessage('error.noSiteConfiguration');
            }

            $site                = $this->siteFinder->getSiteByPageId($this->pageId);
            $newsletterContent   = $this->newsletterRenderService->renderNewsletterPage($newsletterUrl);
            $contentRecord       = BackendUtility::getRecord('pages', $this->pageId);
            $newsletterType      = strtoupper($this->request->getArgument('send'));
            $newsletterChannelId = $newsletterChannel->getChannelId();

            if ($newsletterType === self::NEWSLETTER_SEND_TYPE_TEST) {
                $newsletterChannelId .= $this->getExtensionConfiguration('newsletter/testChannelSuffix') ?? '';
            } else {
                $newsletterChannelId .= $this->getExtensionConfiguration('newsletter/liveChannelSuffix') ?? '';
            }
        } catch (Exception) {
            return $this->forwardFlashMessage('error.noSiteConfiguration');
        }

        try {
            /** @var CreateRequestBuilder $createRequestBuilder */
            $createRequestBuilder = GeneralUtility::makeInstance(CreateRequestBuilder::class);

            // Create the event file request
            $eventRequest = $createRequestBuilder
                ->addChannel($newsletterChannelId)
                ->setEmailBaseAndDownloadUrl(
                    (string) $site->getBase(),
                    (string) $site->getBase()
                )
                ->setEmailBodyType(
                    false,
                    true
                )
                ->setEventDetails(
                    ($newsletterType === self::NEWSLETTER_SEND_TYPE_LIVE) ? $this->generateLiveEventId() : null,
                    null,
                    ($newsletterType === self::NEWSLETTER_SEND_TYPE_LIVE)
                        && $newsletterChannel->isSkipUsedId()
                )
                ->setEmailAdresses(
                    $newsletterChannel->getSender() !== '' ? $newsletterChannel->getSender() : null,
                    $newsletterChannel->getReplyTo() !== '' ? $newsletterChannel->getReplyTo() : null
                )
                ->setEmailSubject($this->getPageTitle($contentRecord, $languageId))
                ->setHtmlBodyEmbedImages($newsletterChannel->getEmbedImages())
                ->setHtmlBodyEncoding('UTF-8')
                ->setHtmlBodyTracking(
                    false,
                    false
                )
                ->setHtmlBodyContent(
                    true,
                    $newsletterContent
                )
                ->addTag($newsletterChannel->getTitle())
                ->addTag($newsletterType)
                ->create();

            $this->universalMessengerService
                ->api()
                ->eventFile()
                ->event($eventRequest);

            // Print some status for TEST
            if ($newsletterType === self::NEWSLETTER_SEND_TYPE_TEST) {
                $this->moduleTemplate->addFlashMessage(
                    $this->translate('newsletter.status.hold'),
                    $this->translate('common.universalMessenger'),
                    ContextualFeedbackSeverity::INFO
                );
            }
        } catch (Exception $exception) {
            $this->logger->error(
                $exception->getMessage(),
                [
                    'exception' => $exception,
                ]
            );

            return $this->forwardFlashMessage('error.exceptionDuringCreate');
        }

        return new ForwardResponse('index');
    }

    /**
     * Renders the newsletter status message.
     *
     * @param NewsletterStatus $status
     *
     * @return void
     */
    private function renderStatusMessage(NewsletterStatus $status): void
    {
        // Default on hold status is displayed if the API is not yet returning a valid status response
        $severity = ContextualFeedbackSeverity::INFO;
        $message  = $this->translate('newsletter.status.hold');

        if ($status->isFailed) {
            $message  = $this->translate('newsletter.status.failed');
            $severity = ContextualFeedbackSeverity::WARNING;
        } elseif ($status->isStopped) {
            $message  = $this->translate('newsletter.status.stopped');
            $severity = ContextualFeedbackSeverity::WARNING;
        } elseif ($status->inQueue) {
            $message  = $this->translate('newsletter.status.queue');
            $severity = ContextualFeedbackSeverity::OK;
        } elseif ($status->isFinished) {
            if ($status->contacted === 1) {
                $message = $this->translate('newsletter.status.finished');
            } else {
                $message = $this->translate(
                    'newsletter.status.finished.plural',
                    [
                        $status->contacted,
                    ]
                );
            }

            $severity = ContextualFeedbackSeverity::OK;
        }

        $this->moduleTemplate->addFlashMessage(
            $message,
            $this->translate('common.universalMessenger'),
            $severity
        );
    }

    /**
     * Returns the status of a newsletter sending for the given newsletter event ID.
     *
     * @param string $newsletterEventId
     *
     * @return NewsletterStatus|null
     */
    private function getNewsletterStatus(string $newsletterEventId): ?NewsletterStatus
    {
        try {
            return $this->universalMessengerService
                ->api()
                ->newsletter()
                ->status($newsletterEventId);
        } catch (ServiceException $exception) {
            $this->logger->error(
                $exception->getMessage(),
                [
                    'exception' => $exception,
                ]
            );
        }

        return null;
    }

    /**
     * Returns the newsletter preview URL.
     *
     * @param int  $pageId
     * @param int  $languageId
     * @param bool $preview
     *
     * @return string
     */
    private function getNewsletterUrl(int $pageId, int $languageId, bool $preview = true): string
    {
        // Call the newsletter preview frontend controller to render the selected page
        // in the mail template style inside the backend iframe.
        $previewUri = PreviewUriBuilder::create($pageId)
            ->withAdditionalQueryParameters([
                'preview'                                 => $preview,
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

    /**
     * Returns the LIVE event ID based on the current site, page and language.
     *
     * @return string
     *
     * @throws SiteNotFoundException
     */
    private function generateLiveEventId(): string
    {
        /** @var SiteLanguage $language */
        $language = $this->request->getAttribute('language')
            ?? $this->request->getAttribute('site')->getDefaultLanguage();

        $site = $this->siteFinder->getSiteByPageId($this->pageId);

        return strtoupper(
            sprintf(
                '%s-%s-%s-%d',
                self::NEWSLETTER_SEND_TYPE_LIVE,
                $site->getIdentifier(),
                $language->getLocale()->getLanguageCode(),
                $this->pageId
            )
        );
    }
}
