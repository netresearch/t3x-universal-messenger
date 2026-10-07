<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 * SPDX-License-Identifier: LicenseRef-Netresearch-Restricted-Use
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Acceptance\Controller;

use Netresearch\UniversalMessenger\Controller\NewsletterPreviewController;
use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'acceptance-test-encryption-key-not-secret';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
        parent::tearDown();
    }

    /** The happy path: the rendered content is returned verbatim with a 200 status. */
    #[Test]
    public function returnsTheRenderedContentOnSuccess(): void
    {
        $renderServiceStub = self::createStub(NewsletterRenderService::class);
        $renderServiceStub
            ->method('renderNewsletterPreviewPage')
            ->willReturn('<p>Hello Newsletter</p>');

        $subject = TestableNewsletterPreviewController::createReady($renderServiceStub);

        $response = $subject->previewAction(10, TestableNewsletterPreviewController::tokenFor(10));

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

        $subject->previewAction(10, TestableNewsletterPreviewController::tokenFor(10));
    }

    /**
     * The token is computed in the test, once setUp() has set the encryption key.
     *
     * @return array<string, array{int, int, int|string}> routed page, requested page, page whose token is sent (or the literal token)
     */
    public static function refusedPreviewRequests(): array
    {
        return [
            'no token'                       => [10, 10, ''],
            'token of another page'          => [10, 10, 11],
            'malformed token'                => [10, 10, 'not-a-token'],
            'page other than the routed one' => [11, 10, 10],
        ];
    }

    /**
     * The preview renders a page only on a request routed to that page and carrying the
     * token the backend module issued for it; anything else is refused before the render
     * service, which fetches the page over HTTP, is called.
     *
     * @param int        $routedPageId
     * @param int        $requestedPageId
     * @param int|string $token           the page whose token is sent, or the literal token
     */
    #[Test]
    #[DataProvider('refusedPreviewRequests')]
    public function refusesAPreviewWithoutTheTokenOfTheRoutedPage(int $routedPageId, int $requestedPageId, int|string $token): void
    {
        if (is_int($token)) {
            $token = TestableNewsletterPreviewController::tokenFor($token);
        }

        $renderServiceMock = $this->createMock(NewsletterRenderService::class);
        $renderServiceMock
            ->expects(self::never())
            ->method('renderNewsletterPreviewPage');

        $subject = TestableNewsletterPreviewController::createReady($renderServiceMock, $routedPageId);

        $response = $subject->previewAction($requestedPageId, $token);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }
}
