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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
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
    protected readonly ModuleTemplateFactory $moduleTemplateFactory;

    /**
     * @var ExtensionConfiguration
     */
    protected ExtensionConfiguration $extensionConfiguration;

    /**
     * @var PageRenderer
     */
    protected readonly PageRenderer $pageRenderer;

    /**
     * @var IconFactory
     */
    protected readonly IconFactory $iconFactory;

    /**
     * @var PersistenceManager
     */
    protected readonly PersistenceManager $persistenceManager;

    /**
     * The selected page ID.
     *
     * @var int
     */
    private int $pageId = 0;

    /**
     * TranslationController constructor.
     *
     * @param ModuleTemplateFactory  $moduleTemplateFactory
     * @param PageRenderer           $pageRenderer
     * @param ExtensionConfiguration $extensionConfiguration
     * @param IconFactory            $iconFactory
     * @param PersistenceManager     $persistenceManager
     */
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        PageRenderer $pageRenderer,
        ExtensionConfiguration $extensionConfiguration,
        IconFactory $iconFactory,
        PersistenceManager $persistenceManager,
    ) {
        $this->extensionConfiguration = $extensionConfiguration;
        $this->persistenceManager     = $persistenceManager;
        $this->moduleTemplateFactory  = $moduleTemplateFactory;
        $this->iconFactory            = $iconFactory;

        $this->pageRenderer = $pageRenderer;
        $this->pageRenderer->loadJavaScriptModule('@typo3/backend/modal.js');
    }

    /**
     * Initialize Action.
     *
     * @return void
     */
    protected function initializeAction(): void
    {
        parent::initializeAction();

        $this->pageId = $this->getPageId($this->request);
    }

    /**
     * Returns the page ID extracted from the given request object.
     *
     * @param ServerRequestInterface $request
     *
     * @return int
     */
    private function getPageId(ServerRequestInterface $request): int
    {
        return (int) ($request->getParsedBody()['id'] ?? $request->getQueryParams()['id'] ?? -1);
    }

    /**
     * @return ResponseInterface
     */
    private function moduleResponse(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->assign('content', $this->view->render());

        //        $this->registerDocHeaderButtons($moduleTemplate);

        $contentPage = BackendUtility::getRecord('pages', $this->pageId);

        // Show button only at pages matching our page type.
        if ($contentPage['doktype'] !== Configuration::getNewsletterPageDokType()) {
            return $moduleTemplate->renderResponse('Backend/BackendModule.html');
        }

DebuggerUtility::var_dump($contentPage['title']);

        return $moduleTemplate->renderResponse('Backend/BackendModule.html');
    }

    /**
     * Shows the textDB entires.
     *
     * @return ResponseInterface
     */
    public function indexAction(): ResponseInterface
    {
        return $this->moduleResponse();
    }
}
