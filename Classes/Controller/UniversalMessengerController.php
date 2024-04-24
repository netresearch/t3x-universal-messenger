<?php

/**
 * This file is part of the package netresearch/nrc-universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrcUniversalMessenger\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

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
    }

    /**
     * @return ResponseInterface
     */
    private function moduleResponse(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->assign('content', $this->view->render());

//        $this->registerDocHeaderButtons($moduleTemplate);

        return $moduleTemplate->renderResponse('Backend/BackendModule.html');
    }

    /**
     * Shows the textDB entires.
     *
     * @return ResponseInterface
     *
     * @throws InvalidQueryException
     */
    public function indexAction(): ResponseInterface
    {
        return $this->moduleResponse();
    }
}
