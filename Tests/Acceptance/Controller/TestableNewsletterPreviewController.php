<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Acceptance\Controller;

use Netresearch\UniversalMessenger\Controller\NewsletterPreviewController;
use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;

/**
 * Widens visibility so the test can inject a real Extbase request without a full
 * Extbase dispatch cycle (processRequest()), which needs a bootstrapped TYPO3
 * container this Acceptance tier deliberately does not provide.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
final class TestableNewsletterPreviewController extends NewsletterPreviewController
{
    /**
     * Injects a real Extbase request, bypassing processRequest().
     *
     * @param RequestInterface $request
     *
     * @return void
     */
    private function setRequestForTesting(RequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * Builds an instance with its response/stream factories and a real Extbase
     * request already injected, ready to call previewAction() on directly.
     * Shared by both the Acceptance and Functional tier tests of this controller.
     *
     * @param NewsletterRenderService $newsletterRenderService
     *
     * @return self a ready-to-use instance for calling previewAction() on directly
     */
    public static function createReady(NewsletterRenderService $newsletterRenderService): self
    {
        $subject = new self($newsletterRenderService);
        $subject->injectResponseFactory(new ResponseFactory());
        $subject->injectStreamFactory(new StreamFactory());

        $psrRequest = (new ServerRequest())->withAttribute(
            'extbase',
            new ExtbaseRequestParameters(),
        );

        $subject->setRequestForTesting(new Request($psrRequest));

        return $subject;
    }
}
