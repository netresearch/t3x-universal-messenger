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
    public function setRequestForTesting(RequestInterface $request): void
    {
        $this->request = $request;
    }
}
