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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Acceptance test for NewsletterPreviewController: calls previewAction() directly
 * with real PSR-7 request/response objects, bypassing the Extbase dispatch cycle
 * (see TestableNewsletterPreviewController). NewsletterRenderService is the
 * controller's only true boundary collaborator (it talks to the render pipeline
 * and an external HTTP fetch), so it is the only stub.
 *
 * The catch-branch scenarios (template not configured) live in the Functional
 * counterpart, Tests/Functional/Controller/NewsletterPreviewControllerTest.php,
 * since that response goes through LocalizationUtility::translate(), which needs
 * a real TYPO3 container this tier deliberately does not provide.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(NewsletterPreviewController::class)]
final class NewsletterPreviewControllerTest extends TestCase
{
    /** The happy path: the rendered content is returned verbatim with a 200 status. */
    #[Test]
    public function returnsTheRenderedContentOnSuccess(): void
    {
        $renderServiceStub = self::createStub(NewsletterRenderService::class);
        $renderServiceStub
            ->method('renderNewsletterPreviewPage')
            ->willReturn('<p>Hello Newsletter</p>');

        $subject = TestableNewsletterPreviewController::createReady($renderServiceStub);

        $response = $subject->previewAction(10);

        self::assertSame(
            200,
            $response->getStatusCode(),
        );
        self::assertSame(
            '<p>Hello Newsletter</p>',
            (string) $response->getBody(),
        );
    }

    /**
     * The catch in previewAction() is scoped to InvalidTemplateResourceException only.
     * Any other exception raised by the render pipeline (e.g. a misconfigured
     * "Preview URL is invalid" RuntimeException) must keep propagating uncaught
     * instead of being masked as a generic "template not configured" response.
     */
    #[Test]
    public function letsAnUnrelatedExceptionPropagateUncaught(): void
    {
        $renderServiceStub = self::createStub(NewsletterRenderService::class);
        $renderServiceStub
            ->method('renderNewsletterPreviewPage')
            ->willThrowException(new RuntimeException('Preview URL is invalid: '));

        $subject = TestableNewsletterPreviewController::createReady($renderServiceStub);

        $this->expectException(RuntimeException::class);

        $subject->previewAction(10);
    }
}
