<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Controller;

use Netresearch\UniversalMessenger\Domain\Repository\NewsletterChannelRepository;
use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use Netresearch\UniversalMessenger\Service\UniversalMessengerService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * AbstractBaseController.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
abstract class AbstractBaseController extends ActionController
{
    /**
     * @var int
     */
    protected const PREVIEW_TYPE_NUMBER = 1_715_682_913;

    /**
     * @var ModuleTemplateFactory
     */
    private readonly ModuleTemplateFactory $moduleTemplateFactory;

    /**
     * @var ModuleTemplate
     */
    protected ModuleTemplate $moduleTemplate;

    /**
     * @var UniversalMessengerService
     */
    protected UniversalMessengerService $universalMessengerService;

    /**
     * @var NewsletterChannelRepository
     */
    protected NewsletterChannelRepository $newsletterChannelRepository;

    /**
     * @var NewsletterRenderService
     */
    protected NewsletterRenderService $newsletterRenderService;

    /**
     * The selected page ID.
     *
     * @var int
     */
    protected int $pageId = 0;

    /**
     * AbstractBaseController constructor.
     *
     * @param ModuleTemplateFactory       $moduleTemplateFactory
     * @param UniversalMessengerService   $universalMessengerService
     * @param NewsletterChannelRepository $newsletterChannelRepository
     * @param NewsletterRenderService     $newsletterRenderService
     */
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        UniversalMessengerService $universalMessengerService,
        NewsletterChannelRepository $newsletterChannelRepository,
        NewsletterRenderService $newsletterRenderService
    ) {
        $this->moduleTemplateFactory       = $moduleTemplateFactory;
        $this->universalMessengerService   = $universalMessengerService;
        $this->newsletterChannelRepository = $newsletterChannelRepository;
        $this->newsletterRenderService     = $newsletterRenderService;
    }

    /**
     * Initialize action.
     *
     * @return void
     */
    protected function initializeAction(): void
    {
        parent::initializeAction();

        $this->pageId         = $this->getPageId();
        $this->moduleTemplate = $this->getModuleTemplate();
    }

    /**
     * The error entry point.
     *
     * @return ResponseInterface
     */
    protected function errorAction(): ResponseInterface
    {
        return $this->moduleTemplate->renderResponse('Backend/UniversalMessenger');
    }

    /**
     * Returns the page ID extracted from the given request object.
     *
     * @return int
     */
    private function getPageId(): int
    {
        return (int) ($this->request->getParsedBody()['id'] ?? $this->request->getQueryParams()['id'] ?? -1);
    }

    /**
     * Returns the module template instance.
     *
     * @return ModuleTemplate
     */
    private function getModuleTemplate(): ModuleTemplate
    {
        $pageRecord = BackendUtility::readPageAccess(
            $this->pageId,
            $this->getBackendUserAuthentication()->getPagePermsClause(Permission::PAGE_SHOW)
        );

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setBodyTag('<body class="typo3-module-universal-messenger">');
        $moduleTemplate->setModuleId('typo3-module-universal-messenger');
        $moduleTemplate->setTitle(
            $this->getLanguageService()->sL(
                'LLL:EXT:universal_messenger/Resources/Private/Language/locallang_mod_um.xlf:mlang_tabs_tab'
            ),
            $pageRecord['title'] ?? ''
        );

        if ($pageRecord !== false) {
            $moduleTemplate
                ->getDocHeaderComponent()
                ->setMetaInformation($pageRecord);
        }

        return $moduleTemplate;
    }

    /**
     * Adds a flash message to the message queue and forward to the error action to abort further processing.
     *
     * @param string                     $key
     * @param ContextualFeedbackSeverity $severity
     *
     * @return ResponseInterface
     */
    protected function forwardFlashMessage(
        string $key,
        ContextualFeedbackSeverity $severity = ContextualFeedbackSeverity::ERROR
    ): ResponseInterface {
        $this->moduleTemplate->addFlashMessage(
            $this->translate($key),
            'Universal Messenger',
            $severity
        );

        return new ForwardResponse('error');
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @return LanguageService
     */
    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Returns the translated language label for the given identifier.
     *
     * @param string $key
     *
     * @return string
     */
    private function translate(string $key): string
    {
        return $this->getLanguageService()->sL(
            'LLL:EXT:universal_messenger/Resources/Private/Language/locallang.xlf:' . $key
        );
    }
}
