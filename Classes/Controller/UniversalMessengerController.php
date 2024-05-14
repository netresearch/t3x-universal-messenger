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
use TYPO3\CMS\Core\Type\Bitmask\Permission;
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
            return $this->moduleTemplate->renderResponse('Backend/UniversalMessenger.html');
        }

DebuggerUtility::var_dump($contentPage['title']);

        return $this->moduleTemplate->renderResponse('Backend/UniversalMessenger.html');
    }

    /**
     * Shows the textDB entires.
     *
     * @return ResponseInterface
     */
    public function indexAction(): ResponseInterface
    {
        $this->moduleTemplate->assign('content', $this->view->render());

        return $this->moduleResponse();
    }
}
