<?php

/**
 * This file is part of the package netresearch/nrc-universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrcUniversalMessenger\Controller;

use Netresearch\NrcUniversalMessenger\Service\NewsletterRenderService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Exception\AccessDeniedException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * UniversalMessengerController.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class NewsletterPreviewController extends ActionController
{
    /**
     * @var NewsletterRenderService
     */
    private readonly NewsletterRenderService $newsletterRenderService;

    /**
     * NewsletterPreviewController constructor.
     *
     * @param NewsletterRenderService $newsletterRenderService
     */
    public function __construct(
        NewsletterRenderService $newsletterRenderService
    ) {
        $this->newsletterRenderService = $newsletterRenderService;
    }

    /**
     * @return void
     *
     * @throws AccessDeniedException
     */
    public function initializePreviewAction(): void
    {
        //        if ($this->getBackendUserAuthentication() === null) {
        //            throw new AccessDeniedException('Backend user authentication is missing.');
        //        }
    }

    /**
     * @param int $pageId
     *
     * @return ResponseInterface
     *
     * @throws SiteNotFoundException
     */
    public function previewAction(int $pageId): ResponseInterface
    {
        return $this->htmlResponse(
            $this->newsletterRenderService->renderNewsletterPage($pageId)
        );
    }
}
