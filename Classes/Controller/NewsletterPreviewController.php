<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Controller;

use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\CssSelector\Exception\ParseException;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * NewsletterPreviewController.
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
     * This action is used to display a preview of a newsletter as it would look in the email.
     *
     * @param int $pageId
     *
     * @return ResponseInterface
     *
     * @throws ParseException
     * @throws RuntimeException
     */
    public function previewAction(int $pageId): ResponseInterface
    {
        return $this->htmlResponse(
            $this->newsletterRenderService->renderNewsletterPreviewPage(
                $this->request,
                $pageId
            )
        );
    }
}
