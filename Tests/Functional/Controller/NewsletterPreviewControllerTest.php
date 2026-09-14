<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Functional\Controller;

use Netresearch\UniversalMessenger\Controller\NewsletterPreviewController;
use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use Netresearch\UniversalMessenger\Tests\Acceptance\Controller\TestableNewsletterPreviewController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException;

/**
 * Functional counterpart to Tests/Acceptance/Controller/NewsletterPreviewControllerTest.php:
 * covers only the catch-branch scenarios, since previewAction()'s error response goes through
 * LocalizationUtility::translate(), which needs a real TYPO3 container (LanguageServiceFactory
 * is DI-constructed) the Acceptance tier deliberately does not provide. NewsletterRenderService
 * is the controller's only true boundary collaborator, so it is the only stub. Builds the
 * controller under test via the Acceptance tier's TestableNewsletterPreviewController::
 * createReady(): it has no tier-specific dependency, so a second copy (class or builder)
 * would be pure duplication.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(NewsletterPreviewController::class)]
final class NewsletterPreviewControllerTest extends FunctionalTestCase
{
    /**
     * @var non-empty-string[]
     */
    protected array $testExtensionsToLoad = [
        'netresearch/universal-messenger',
    ];

    /**
     * A classic (non-Site-Set) site without the "Example Newsletter Template" static
     * template leaves plugin.tx_universalmessenger.view.templatePathAndFilename unset,
     * which the container-template render chain (NewsletterRenderService::
     * renderNewsletterContainer() -> the view's render()) surfaces as
     * InvalidTemplateResourceException. previewAction() must catch it and respond
     * with a non-200 status carrying a translated, actionable message, not let the
     * raw Fluid exception propagate as a "Whoops" page.
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
            ->expects($this->once())
            ->method('error')
            ->with(
                $exception->getMessage(),
                self::callback(
                    static fn (array $context): bool => ($context['exception'] === $exception) && ($context['pageId'] === 10),
                ),
            );

        $subject = TestableNewsletterPreviewController::createReady($renderServiceStub);
        $subject->setLogger($loggerMock);

        $response = $subject->previewAction(10);

        self::assertMissingTemplateResponse($response);
    }

    /**
     * The logger call is nullsafe ($this->logger?->error(...)): NewsletterPreviewController
     * only gets a logger injected via TYPO3's DI container (LoggerAwareInterface), so any
     * code path constructing it directly, like TestableNewsletterPreviewController::createReady(),
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

        $subject = TestableNewsletterPreviewController::createReady($renderServiceStub);

        $response = $subject->previewAction(10);

        self::assertMissingTemplateResponse($response);
    }

    /**
     * Asserts the 503 response body is exactly the translated
     * error.missingNewsletterTemplateConfiguration XLF source. An exact match, rather than a
     * substring, rules out three distinct regressions at once: the sibling
     * error.missingTypoScriptConfiguration string (both share a "Please assign the ... site
     * set" sentence), the pre-fix hardcoded English literal (which shared this key's opening
     * sentence), and any incidental future overlap between them.
     *
     * @param ResponseInterface $response
     *
     * @return void
     */
    private static function assertMissingTemplateResponse(ResponseInterface $response): void
    {
        self::assertSame(
            503,
            $response->getStatusCode(),
        );
        self::assertSame(
            'Newsletter template is not configured for this site. Please assign the'
            . ' "Universal Messenger" site set to this site (or include the "Example'
            . ' Newsletter Template" static TypoScript template).',
            (string) $response->getBody(),
        );
    }
}
