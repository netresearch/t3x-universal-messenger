<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Controller;

use Exception;

use function in_array;

use Netresearch\Sdk\UniversalMessenger\Exception\ServiceException;
use Netresearch\Sdk\UniversalMessenger\Model\NewsletterStatus;
use Netresearch\Sdk\UniversalMessenger\RequestBuilder\EventFile\CreateRequestBuilder;
use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Domain\Model\NewsletterChannel;
use Netresearch\UniversalMessenger\Domain\Repository\NewsletterChannelRepository;
use Netresearch\UniversalMessenger\Repository\EventFileRepository;
use Netresearch\UniversalMessenger\Repository\NewsletterRepository;
use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

use function sprintf;

use TYPO3\CMS\Backend\Domain\Repository\Localization\LocalizationRepository;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\Buttons\ButtonInterface;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\RawRecord;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException;

/**
 * UniversalMessengerController.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
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
     * @var EventFileRepository
     */
    private EventFileRepository $eventFileRepository;

    /**
     * @var NewsletterRepository
     */
    private NewsletterRepository $newsletterRepository;

    /**
     * UniversalMessengerController constructor.
     *
     * @param ModuleTemplateFactory       $moduleTemplateFactory
     * @param Configuration               $configuration
     * @param NewsletterChannelRepository $newsletterChannelRepository
     * @param NewsletterRenderService     $newsletterRenderService
     * @param ComponentFactory            $componentFactory
     * @param LocalizationRepository      $localizationRepository
     * @param SiteFinder                  $siteFinder
     * @param EventFileRepository         $eventFileRepository
     * @param NewsletterRepository        $newsletterRepository
     */
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        Configuration $configuration,
        NewsletterChannelRepository $newsletterChannelRepository,
        NewsletterRenderService $newsletterRenderService,
        ComponentFactory $componentFactory,
        LocalizationRepository $localizationRepository,
        SiteFinder $siteFinder,
        EventFileRepository $eventFileRepository,
        NewsletterRepository $newsletterRepository,
    ) {
        parent::__construct(
            $moduleTemplateFactory,
            $configuration,
            $newsletterChannelRepository,
            $newsletterRenderService,
            $componentFactory,
            $localizationRepository,
        );

        $this->siteFinder           = $siteFinder;
        $this->eventFileRepository  = $eventFileRepository;
        $this->newsletterRepository = $newsletterRepository;
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
        if (($languageButton = $this->makeLanguageSwitchButton()) instanceof ButtonInterface) {
            $buttonBar->addButton(
                $languageButton,
                ButtonBar::BUTTON_POSITION_LEFT,
                0,
            );
        }

        $contentRecord = $this->getPageRecord();
        $channelUid    = (int) ($contentRecord['universal_messenger_channel'] ?? 0);

        // Check if the selected page matches our newsletter page type, is visible,
        // has a configured channel and the current backend user is permitted to
        // use it. Kept after the doc-header UI above (unlike createAction()'s
        // otherwise-identical guard): the language switcher must stay available
        // on the rejection view too, e.g. a page hidden in the current language
        // may be reachable after switching to another one.
        $authorizationFailure = $this->getChannelAuthorizationFailure(
            $contentRecord,
            $channelUid,
        );

        if ($authorizationFailure !== null) {
            return $this->forwardFlashMessage(
                $authorizationFailure,
                $this->getAuthorizationFailureSeverity($authorizationFailure),
            );
        }

        $newsletterUrl = $this->getNewsletterUrl($this->pageId);

        // Check if the created preview URL is valid
        if (!$this->isUrlValid($newsletterUrl)) {
            return $this->forwardFlashMessage('error.noSiteConfiguration');
        }

        // Check if a newsletter status is available
        $newsletterEventId = $this->generateLiveEventId();
        $newsletterChannel = $this->newsletterChannelRepository
            ->findByUid($channelUid);

        $newsletterStatus = $this->getNewsletterStatus($newsletterEventId);

        // Show the status if available
        if (($newsletterStatus instanceof NewsletterStatus)
            && ($newsletterStatus->error === null)
        ) {
            if (($newsletterChannel instanceof NewsletterChannel)
                && $newsletterChannel->isSkipUsedId()
            ) {
                $this->view->assign('disableLiveButton', true);
            }

            $this->renderStatusMessage($newsletterStatus);
        }

        $this->view->assign('pageId', $this->pageId);
        $this->view->assign('pageTitle', $this->getPageTitle($contentRecord));
        $this->view->assign('previewUrl', $newsletterUrl);
        $this->view->assign('newsletterChannel', $newsletterChannel);

        // The module content is rendered through the Extbase view, whose template paths
        // come from the extension TypoScript (module.tx_universalmessenger.view). When that
        // TypoScript is not loaded — e.g. the "netresearch/universal-messenger" site set is
        // not assigned to the current site — the view falls back to the Extbase default path
        // and cannot resolve its template. Catch that case and show an actionable message
        // instead of letting the raw Fluid exception surface to the editor.
        try {
            $renderedContent = $this->view->render();
        } catch (InvalidTemplateResourceException) {
            return $this->forwardFlashMessage('error.missingTypoScriptConfiguration');
        }

        $this->moduleTemplate->assign('content', $renderedContent);

        return $this->moduleTemplate->renderResponse('Backend/UniversalMessenger');
    }

    /**
     * Returns the localized page title.
     *
     * @param array<string, mixed>|null $pageRecord
     *
     * @return string
     */
    private function getPageTitle(?array $pageRecord): string
    {
        $localizedRecord = $this->localizationRepository->getRecordTranslation(
            'pages',
            $this->pageId,
            $this->currentSelectedLanguage,
            $this->getBackendUserAuthentication()->workspace,
        );

        if ($localizedRecord instanceof RawRecord) {
            return (string) ($localizedRecord->get('title') ?? '');
        }

        return (string) ($pageRecord['title'] ?? '');
    }

    /**
     * Returns the raw pages record of the currently selected page.
     *
     * Wrapped so it can be doubled in tests without a database.
     *
     * @return array<string, int|string|null>|null
     */
    protected function getPageRecord(): ?array
    {
        /** @var array<string, int|string|null>|null $pageRecord */
        $pageRecord = BackendUtility::getRecord('pages', $this->pageId);

        return $pageRecord;
    }

    /**
     * Determines why $channelUid may not be used to dispatch a send from
     * $pageRecord, or returns NULL when it may.
     *
     * The single source of truth for the newsletter module's authorization
     * rule: the page must match the configured newsletter doktype, must not
     * be hidden, must have a configured channel matching $channelUid, and the
     * current backend user must be permitted to use that channel. Both
     * indexAction() (deciding whether to render the send form) and
     * createAction() (deciding whether to actually dispatch) go through this
     * one method, so the two can never drift apart and silently reopen an
     * authorization gap between "what is offered" and "what is accepted."
     *
     * @param array<string, int|string|null>|null $pageRecord
     * @param int                                 $channelUid
     *
     * @return string|null The flash message key naming the failure reason, or NULL when authorized
     */
    protected function getChannelAuthorizationFailure(?array $pageRecord, int $channelUid): ?string
    {
        if (($pageRecord === null)
            || ((int) $pageRecord['doktype'] !== $this->configuration->getNewsletterPageDokType())
        ) {
            return 'error.pageNotAllowed';
        }

        if (!isset($pageRecord['hidden'])
            || ($pageRecord['hidden'] >= 1)
        ) {
            return 'error.pageHidden';
        }

        if (!isset($pageRecord['universal_messenger_channel'])
            || ((int) $pageRecord['universal_messenger_channel'] <= 0)
        ) {
            return 'error.missingChannelConfiguration';
        }

        if (((int) $pageRecord['universal_messenger_channel'] !== $channelUid)
            || !in_array(
                $channelUid,
                $this->getNewsletterChannelPermissions(),
                true,
            )
        ) {
            return 'error.accessNotAllowed';
        }

        return null;
    }

    /**
     * Returns the flash message severity for an authorization-failure reason
     * from getChannelAuthorizationFailure(). Only a missing/wrong-doktype page
     * is informational, everything else (hidden, unconfigured, unauthorized) is
     * an error the editor needs to act on.
     *
     * @param string $authorizationFailure
     *
     * @return ContextualFeedbackSeverity
     */
    protected function getAuthorizationFailureSeverity(string $authorizationFailure): ContextualFeedbackSeverity
    {
        return ($authorizationFailure === 'error.pageNotAllowed')
            ? ContextualFeedbackSeverity::INFO
            : ContextualFeedbackSeverity::ERROR;
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
        if ($backendUserAuthentication->user['universal_messenger_channels'] ?? false) {
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
    public function createAction(?NewsletterChannel $newsletterChannel = null): ResponseInterface
    {
        // Sending is irreversible and must never be reachable by navigation alone.
        // A GET carrying the send parameters can be bookmarked and reloaded, and —
        // worst of all — TYPO3 replays such a pending action after the editor logs
        // in again, which would fire a live send without ever asking. Only an
        // explicitly submitted form may reach the webservice.
        if ($this->request->getMethod() !== 'POST') {
            return $this->forwardFlashMessage('error.sendNotConfirmed');
        }

        // Check if the submitted request is valid
        if (!$newsletterChannel instanceof NewsletterChannel
            || !$this->request->hasArgument('send')
        ) {
            return $this->forwardFlashMessage('error.invalidRequest');
        }

        $contentRecord = $this->getPageRecord();

        // The channel above is populated straight from the submitted "newsletterChannel"
        // hidden field, i.e. Extbase resolves it to any NewsletterChannel record by UID.
        // Re-run the same authorization rule indexAction() uses before rendering the
        // send form, this time against the submitted channel, before touching any
        // collaborator that talks to the webservice. Every possible reason collapses
        // into one generic message: unlike indexAction()'s trusted, already-rendered
        // view, this is the path a crafted POST reaches, and leaking which specific
        // check failed would help an attacker probe the guard.
        if ($this->getChannelAuthorizationFailure(
            $contentRecord,
            (int) $newsletterChannel->getUid(),
        ) !== null) {
            return $this->forwardFlashMessage('error.accessNotAllowed');
        }

        try {
            $newsletterUrl = $this->getNewsletterUrl($this->pageId, false);

            // Check if the created newsletter URL is valid
            if (!$this->isUrlValid($newsletterUrl)) {
                return $this->forwardFlashMessage('error.noSiteConfiguration');
            }

            $site                = $this->siteFinder->getSiteByPageId($this->pageId);
            $newsletterContent   = $this->newsletterRenderService->renderNewsletterPage($newsletterUrl);
            $newsletterType      = strtoupper((string) $this->request->getArgument('send'));
            $newsletterChannelId = $newsletterChannel->getChannelId();

            if ($newsletterType === self::NEWSLETTER_SEND_TYPE_TEST) {
                $newsletterChannelId .= $this->configuration->getExtensionSetting('newsletter/testChannelSuffix') ?? '';
            } else {
                $newsletterChannelId .= $this->configuration->getExtensionSetting('newsletter/liveChannelSuffix') ?? '';
            }
        } catch (Exception) {
            return $this->forwardFlashMessage('error.noSiteConfiguration');
        }

        try {
            /** @var CreateRequestBuilder $createRequestBuilder */
            $createRequestBuilder = GeneralUtility::makeInstance(CreateRequestBuilder::class);

            // TODO Make subject prefix configurable
            $pageTitle = $this->getPageTitle($contentRecord);
            if ($newsletterType === self::NEWSLETTER_SEND_TYPE_TEST) {
                $pageTitle = 'TEST: ' . $pageTitle;
            }

            // Create the event file request
            $eventRequest = $createRequestBuilder
                ->addChannel($newsletterChannelId)
                ->setEmailBaseAndDownloadUrl(
                    (string) $site->getBase(),
                    (string) $site->getBase(),
                )
                ->setEmailBodyType(
                    false,
                    true,
                )
                ->setEventDetails(
                    ($newsletterType === self::NEWSLETTER_SEND_TYPE_LIVE) ? $this->generateLiveEventId() : null,
                    null,
                    ($newsletterType === self::NEWSLETTER_SEND_TYPE_LIVE)
                        && $newsletterChannel->isSkipUsedId(),
                )
                ->setEmailAdresses(
                    $newsletterChannel->getSender() !== '' ? $newsletterChannel->getSender() : null,
                    $newsletterChannel->getReplyTo() !== '' ? $newsletterChannel->getReplyTo() : null,
                )
                ->setEmailSubject($pageTitle)
                ->setHtmlBodyBaseAndDownloadUrl(null, (string) $site->getBase(), null)
                ->setHtmlBodyEmbedImages($newsletterChannel->getEmbedImages())
                ->setHtmlBodyEncoding('UTF-8')
                ->setHtmlBodyTracking(
                    false,
                    false,
                )
                ->setHtmlBodyContent(
                    true,
                    $newsletterContent,
                )
                ->addTag($newsletterChannel->getTitle())
                ->addTag($newsletterType)
                ->create();

            $this->eventFileRepository
                ->sendEventFile($eventRequest);

            // Print some status for TEST
            if ($newsletterType === self::NEWSLETTER_SEND_TYPE_TEST) {
                $this->moduleTemplate->addFlashMessage(
                    $this->translate('newsletter.status.hold'),
                    $this->translate('common.universalMessenger'),
                    ContextualFeedbackSeverity::INFO,
                );
            }
        } catch (Exception $exception) {
            $this->logger?->error(
                $exception->getMessage(),
                [
                    'exception' => $exception,
                ],
            );

            return $this->forwardFlashMessage('error.exceptionDuringCreate');
        }

        return new ForwardResponse('index');
    }

    /**
     * Renders the newsletter status message.
     *
     * @param NewsletterStatus $newsletterStatus
     *
     * @return void
     */
    private function renderStatusMessage(NewsletterStatus $newsletterStatus): void
    {
        // Default on hold status is displayed if the API is not yet returning a valid status response
        $severity = ContextualFeedbackSeverity::INFO;
        $message  = $this->translate('newsletter.status.hold');

        if ($newsletterStatus->isFailed) {
            $message  = $this->translate('newsletter.status.failed');
            $severity = ContextualFeedbackSeverity::WARNING;
        } elseif ($newsletterStatus->isStopped) {
            $message  = $this->translate('newsletter.status.stopped');
            $severity = ContextualFeedbackSeverity::WARNING;
        } elseif ($newsletterStatus->inQueue) {
            $message  = $this->translate('newsletter.status.queue');
            $severity = ContextualFeedbackSeverity::OK;
        } elseif ($newsletterStatus->isFinished) {
            if ($newsletterStatus->contacted === 1) {
                $message = $this->translate('newsletter.status.finished');
            } else {
                $message = $this->translate(
                    'newsletter.status.finished.plural',
                    [
                        $newsletterStatus->contacted,
                    ],
                );
            }

            $severity = ContextualFeedbackSeverity::OK;
        }

        $this->moduleTemplate->addFlashMessage(
            $message,
            $this->translate('common.universalMessenger'),
            $severity,
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
            return $this->newsletterRepository->getStatus($newsletterEventId);
        } catch (ServiceException $exception) {
            $this->logger?->error(
                $exception->getMessage(),
                [
                    'exception' => $exception,
                ],
            );
        }

        return null;
    }

    /**
     * Returns the newsletter preview URL.
     *
     * @param int  $pageId
     * @param bool $preview
     *
     * @return string
     */
    private function getNewsletterUrl(int $pageId, bool $preview = true): string
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
            ->withLanguage($this->currentSelectedLanguage)
            ->buildUri();

        if (!$previewUri instanceof UriInterface) {
            return '';
        }

        // A site configured with a relative base (base: /, the TYPO3 v14 default) produces
        // a host-less preview URI. Turn it into an absolute URL using the current backend
        // request, so it passes URL validation and can be fetched when rendering the
        // newsletter page for sending.
        if ($previewUri->getHost() === '') {
            $requestUri = $this->request->getUri();
            $previewUri = $previewUri
                ->withScheme($requestUri->getScheme())
                ->withHost($requestUri->getHost())
                ->withPort($requestUri->getPort());
        }

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
        /** @var SiteLanguage|null $language */
        $language = $this->request->getAttribute('language');
        $site     = $this->request->getAttribute('site');

        $language ??= $site?->getDefaultLanguage();

        return strtoupper(
            sprintf(
                '%s-%s-%s-%d',
                self::NEWSLETTER_SEND_TYPE_LIVE,
                $this->siteFinder->getSiteByPageId($this->pageId)->getIdentifier(),
                $language?->getLocale()->getLanguageCode() ?? 'en',
                $this->pageId,
            ),
        );
    }
}
