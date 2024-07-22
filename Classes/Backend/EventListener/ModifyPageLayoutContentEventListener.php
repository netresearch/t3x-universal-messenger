<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Backend\EventListener;

use Netresearch\UniversalMessenger\Configuration;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Event listener to add a link button to the Universal Messenger module to the default button bar.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
final class ModifyPageLayoutContentEventListener
{
    /**
     * Invokes the event listener.
     *
     * @throws RouteNotFoundException
     */
    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $view        = $event->getModuleTemplate();
        $buttonBar   = $view->getDocHeaderComponent()->getButtonBar();
        $pageId      = $this->getPageId($event->getRequest());
        $contentPage = BackendUtility::getRecord('pages', $pageId);

        // Show button only at pages matching our page type.
        if ($contentPage['doktype'] !== Configuration::getNewsletterPageDokType()) {
            return;
        }

        $uri = (string) $this->getUriBuilder()
            ->buildUriFromRoute(
                'netresearch_universal_messenger',
                [
                    'id' => $pageId,
                ]
            );

        $returnButton = $buttonBar->makeLinkButton()
            ->setHref($uri)
            ->setTitle(
                $this->getLanguageService()->sL(
                    'LLL:EXT:universal_messenger/Resources/Private/Language/locallang_mod_um.xlf:openInUniversalMessenger'
                )
            )
            ->setIcon(
                $this->getIconFactory()->getIcon(
                    'actions-file-view',
                    Icon::SIZE_SMALL
                )
            )
            ->setShowLabelText(true);

        $buttonBar->addButton(
            $returnButton,
            ButtonBar::BUTTON_POSITION_LEFT,
            3
        );
    }

    /**
     * Returns an instance of the language service.
     *
     * @return LanguageService
     */
    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @return UriBuilder
     */
    private function getUriBuilder(): UriBuilder
    {
        return GeneralUtility::makeInstance(UriBuilder::class);
    }

    /**
     * @return IconFactory
     */
    private function getIconFactory(): IconFactory
    {
        return GeneralUtility::makeInstance(IconFactory::class);
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
        return (int) ($request->getParsedBody()['id'] ?? $request->getQueryParams()['id'] ?? 0);
    }
}
