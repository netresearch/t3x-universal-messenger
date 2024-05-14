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
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * UniversalMessengerController.
 *
 * @author  Thomas Schöne <thomas.schoene@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class UniversalMessengerController extends ActionController
{
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
     * @param ModuleTemplateFactory  $moduleTemplateFactory
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
     * Returns the page ID extracted from the given request object.
     *
     * @return int
     */
    private function getPageId(): int
    {
        return (int) ($this->request->getParsedBody()['id'] ?? $this->request->getQueryParams()['id'] ?? -1);
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
     * Returns the module template instance.
     *
     * @return ModuleTemplate
     */
    private function getModuleTemplate(): ModuleTemplate
    {
        $moduleTemplate   = $this->moduleTemplateFactory->create($this->request);
        $permissionClause = $this->getBackendUserAuthentication()->getPagePermsClause(Permission::PAGE_SHOW);
        $pageRecord       = BackendUtility::readPageAccess($this->pageId, $permissionClause);

        if ($pageRecord !== false) {
            $moduleTemplate
                ->getDocHeaderComponent()
                ->setMetaInformation($pageRecord);
        }

        return $moduleTemplate;
    }

    /**
     * @return ResponseInterface
     */
    private function moduleResponse(): ResponseInterface
    {
        //        $this->registerDocHeaderButtons($moduleTemplate);

        $contentPage = BackendUtility::getRecord('pages', $this->pageId);

        // Show button only at pages matching our page type.
        if ($contentPage['doktype'] !== Configuration::getNewsletterPageDokType()) {
            $this->moduleTemplate->addFlashMessage(
                $this->translate('error.page_not_allowed'),
                'Universal Messenger',
                ContextualFeedbackSeverity::INFO
            );

            return new ForwardResponse('error');
        }

        // Check if backend user is allowed to access this newsletter
        if (!GeneralUtility::inList(
            $this->getBackendUserAuthentication()->user['universal_messenger_channels'],
            $contentPage['universal_messenger_channel']
        )) {
            $this->moduleTemplate->addFlashMessage(
                $this->translate('error.access_not_allowed'),
                'Universal Messenger',
                ContextualFeedbackSeverity::ERROR
            );

            return new ForwardResponse('error');
        }

DebuggerUtility::var_dump($contentPage['title']);

        return $this->moduleTemplate->renderResponse('Backend/UniversalMessenger.html');
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

    /**
     * The main entry point.
     *
     * @return ResponseInterface
     */
    public function indexAction(): ResponseInterface
    {
        $this->moduleTemplate->assign('content', $this->view->render());

        return $this->moduleResponse();
    }

    /**
     * The error entry point.
     *
     * @return ResponseInterface
     */
    public function errorAction(): ResponseInterface
    {
        return $this->moduleTemplate->renderResponse('Backend/UniversalMessenger.html');
    }
}
