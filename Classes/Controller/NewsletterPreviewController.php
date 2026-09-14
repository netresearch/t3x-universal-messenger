<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Controller;

use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException;

/**
 * NewsletterPreviewController.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
class NewsletterPreviewController extends ActionController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

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
        NewsletterRenderService $newsletterRenderService,
    ) {
        $this->newsletterRenderService = $newsletterRenderService;
    }

    /**
     * This action is used to display a preview of a newsletter as it would look in the email.
     *
     * @param int $pageId
     *
     * @return ResponseInterface
     */
    public function previewAction(int $pageId): ResponseInterface
    {
        // See NewsletterRenderService::isNewsletterContainerTemplateConfigured() for why the
        // container template can be unconfigured. UniversalMessengerController::indexAction()
        // checks this upfront and shows a real, properly styled TYPO3 flash message instead of
        // embedding the preview iframe, so an editor should not normally reach this catch
        // block. It stays as a safety net: this response is also what createAction() fetches
        // to build the real dispatch body, so a non-200 status here is load-bearing regardless:
        // it makes that fetch fail loudly (RuntimeException) rather than silently mailing this
        // error message to real newsletter recipients as if it were the newsletter content.
        try {
            $content = $this->newsletterRenderService->renderNewsletterPreviewPage(
                $this->request,
                $pageId,
            );
        } catch (InvalidTemplateResourceException $exception) {
            $this->logger?->error(
                $exception->getMessage(),
                [
                    'exception' => $exception,
                    'pageId'    => $pageId,
                ],
            );

            return $this->htmlResponse(
                // translate() is typed to return null when the key is not found (this one
                // stays registered in locallang.xlf as long as this diff does), so keep the
                // ?? '' even though that currently can't happen here.
                LocalizationUtility::translate(
                    'LLL:EXT:universal_messenger/Resources/Private/Language/locallang.xlf:'
                    . 'error.missingNewsletterTemplateConfiguration',
                ) ?? '',
            )->withStatus(
                503,
                'Newsletter template not configured',
            );
        }

        return $this->htmlResponse($content);
    }
}
