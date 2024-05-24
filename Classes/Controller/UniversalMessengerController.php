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
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * UniversalMessengerController.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class UniversalMessengerController extends ActionController
{
    /**
     * @var int
     */
    private const PREVIEW_TYPE_NUMBER = 1715682913;

    /**
     * @var ModuleTemplateFactory
     */
    private readonly ModuleTemplateFactory $moduleTemplateFactory;

    /**
     * @var ModuleTemplate
     */
    private ModuleTemplate $moduleTemplate;

    /**
     * The selected page ID.
     *
     * @var int
     */
    private int $pageId = 0;

    /**
     * UniversalMessengerController constructor.
     *
     * @param ModuleTemplateFactory $moduleTemplateFactory
     */
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory
    ) {
        $this->moduleTemplateFactory  = $moduleTemplateFactory;
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
        if ($contentPage['doktype'] !== Configuration::getNewsletterPageDokType()) {
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

        $this->view->assign('pageId', $this->pageId);
        $this->view->assign('pageTitle', $contentPage['title']);
        $this->view->assign('previewUrl', $previewUrl);

        $this->moduleTemplate->assign('content', $this->view->render());

        return $this->moduleTemplate->renderResponse('Backend/UniversalMessenger.html');
    }

    /**
     * The error entry point.
     *
     * @return ResponseInterface
     */
    protected function errorAction(): ResponseInterface
    {
        return $this->moduleTemplate->renderResponse('Backend/UniversalMessenger.html');
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
                'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang_mod_um.xlf:mlang_tabs_tab'
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
    private function forwardFlashMessage(
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
    private function getBackendUserAuthentication(): BackendUserAuthentication
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
            'LLL:EXT:nrc_universal_messenger/Resources/Private/Language/locallang.xlf:' . $key
        );
    }
}
