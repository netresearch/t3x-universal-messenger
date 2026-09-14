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
use Psr\Log\LoggerInterface;
use RuntimeException;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException;

/**
 * Acceptance test for NewsletterPreviewController: calls previewAction() directly
 * with real PSR-7 request/response objects, bypassing the Extbase dispatch cycle
 * (see TestableNewsletterPreviewController). NewsletterRenderService is the
 * controller's only true boundary collaborator (it talks to the render pipeline
 * and an external HTTP fetch), so it is the only stub.
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

        $subject = $this->createController($renderServiceStub);

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
     * A classic (non-Site-Set) site without the "Example Newsletter Template" static
     * template leaves plugin.tx_universalmessenger.view.templatePathAndFilename unset,
     * which the container-template render chain (NewsletterRenderService::
     * renderNewsletterContainer() -> the view's render()) surfaces as
     * InvalidTemplateResourceException. previewAction() must catch it and respond
     * with a non-200 status carrying a plain, actionable message, not let the raw
     * Fluid exception propagate as a "Whoops" page.
     *
     * The status must genuinely differ from 200, not just carry an error-looking body:
     * createAction() fetches this same preview URL over HTTP to build the real dispatch
     * content, and NewsletterRenderService::getContentFromUrl() only treats a response
     * as a failure when its status is not exactly 200. A 200 here would make that fetch
     * succeed and mail this error text to real newsletter recipients as if it were the
     * newsletter itself, worse than the original bug.
     */
    #[Test]
    public function respondsWithANonSuccessStatusWhenTheTemplateIsNotConfigured(): void
    {
        $exception = new InvalidTemplateResourceException(
            'Tried resolving a template file for controller action "Default->Default"',
            1257246929,
        );

        $renderServiceStub = self::createStub(NewsletterRenderService::class);
        $renderServiceStub
            ->method('renderNewsletterPreviewPage')
            ->willThrowException($exception);

        $loggerMock = self::createMock(LoggerInterface::class);
        $loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                $exception->getMessage(),
                self::callback(
                    static fn (array $context): bool => ($context['exception'] === $exception) && ($context['pageId'] === 10),
                ),
            );

        $subject = $this->createController($renderServiceStub);
        $subject->setLogger($loggerMock);

        $response = $subject->previewAction(10);

        self::assertSame(
            503,
            $response->getStatusCode(),
        );
        self::assertStringContainsString(
            'Newsletter template is not configured for this site.',
            (string) $response->getBody(),
        );
        self::assertStringNotContainsString(
            'InvalidTemplateResourceException',
            (string) $response->getBody(),
            'the response must carry a plain message, not the raw exception class/trace',
        );
    }

    /**
     * The logger call is nullsafe ($this->logger?->error(...)): NewsletterPreviewController
     * only gets a logger injected via TYPO3's DI container (LoggerAwareInterface), so any
     * code path constructing it directly, like this test suite's own createController(),
     * runs with $this->logger still null. previewAction() must keep working (and keep
     * returning the same 503 response) even without a logger set.
     */
    #[Test]
    public function respondsWithANonSuccessStatusWhenTheTemplateIsNotConfiguredAndNoLoggerIsSet(): void
    {
        $renderServiceStub = self::createStub(NewsletterRenderService::class);
        $renderServiceStub
            ->method('renderNewsletterPreviewPage')
            ->willThrowException(new InvalidTemplateResourceException(
                'missing template',
                1257246929,
            ));

        $subject = $this->createController($renderServiceStub);

        $response = $subject->previewAction(10);

        self::assertSame(
            503,
            $response->getStatusCode(),
        );
        self::assertStringContainsString(
            'Newsletter template is not configured for this site.',
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

        $subject = $this->createController($renderServiceStub);

        $this->expectException(RuntimeException::class);

        $subject->previewAction(10);
    }

    /** Builds the controller under test with its response/stream factories and a real Extbase request injected. */
    private function createController(NewsletterRenderService $newsletterRenderService): TestableNewsletterPreviewController
    {
        $subject = new TestableNewsletterPreviewController($newsletterRenderService);
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
