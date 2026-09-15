<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit\Controller;

use Netresearch\Sdk\UniversalMessenger\Exception\ServiceException;
use Netresearch\Sdk\UniversalMessenger\Request\Event;
use Netresearch\Sdk\UniversalMessenger\Request\Event\Data;
use Netresearch\Sdk\UniversalMessenger\Request\Event\Data\Email;
use Netresearch\Sdk\UniversalMessenger\Request\Event\Data\Email\HtmlText;
use Netresearch\Sdk\UniversalMessenger\Request\Event\Destination;
use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Controller\UniversalMessengerController;
use Netresearch\UniversalMessenger\Domain\Model\NewsletterChannel;
use Netresearch\UniversalMessenger\Repository\EventFileRepository;
use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use TYPO3\CMS\Backend\Domain\Repository\Localization\LocalizationRepository;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Domain\RawRecord;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests that sending a newsletter cannot be triggered by a replayable request,
 * and that it can only be dispatched through the channel actually configured
 * and permitted for the current page.
 *
 * A live send is irreversible. It must never be reachable by navigation alone.
 * Neither by a bookmark or reload, nor by TYPO3 replaying a pending action after
 * the editor logged in again. And it must never be redirectable to a channel
 * (with its own sender/reply-to/recipients) the current page and backend user
 * are not authorized for.
 *
 * indexAction() itself is not exercised by this suite: it touches
 * ModuleTemplate (a final TYPO3 v14 core class, obtainable only through a
 * real container) to build the doc-header UI before its guard runs, by
 * design, so the language switcher stays available on a rejected page too
 * (see the comment at its call site).
 * That call site's own wiring into forwardFlashMessage() is therefore only
 * reachable through a real container, not this unit-test harness;
 * getAuthorizationFailureSeverity() itself is fully covered below, isolated
 * from that collaborator chain.
 *
 * createAction()'s TEST-send success path (the `newsletter.status.hold`
 * flash message) is covered below via `addModuleFlashMessage()`, the same
 * overridable helper every rejection path already goes through via
 * forwardFlashMessage(), see
 * createActionSendsATestNewsletterAndAddsTheHoldStatusMessage().
 * The TEST/LIVE request-building logic that runs immediately before it
 * (channel suffix, tag, subject prefix) is covered below by letting the
 * mocked EventFileRepository throw once it has recorded the built request,
 * which both proves what was built and exercises the exception-handling
 * catch block without ever reaching that line.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(UniversalMessengerController::class)]
final class UniversalMessengerControllerTest extends UnitTestCase
{
    /**
     * @var int
     */
    private const NEWSLETTER_PAGE_DOKTYPE = 20;

    /**
     * @var int
     */
    private const CONFIGURED_CHANNEL_UID = 5;

    /**
     * @var string
     */
    private const TEST_CHANNEL_SUFFIX = '_Test';

    /**
     * @var string
     */
    private const LIVE_CHANNEL_SUFFIX = '_Live';

    /**
     * Every HTTP method that must not be able to trigger a send.
     *
     * @return array<string, array{string}>
     */
    public static function nonSubmittingHttpMethods(): array
    {
        return [
            'GET'    => ['GET'],
            'HEAD'   => ['HEAD'],
            'DELETE' => ['DELETE'],
        ];
    }

    /**
     * A replayable GET/HEAD/DELETE carrying send parameters must not trigger a live send.
     */
    #[Test]
    #[DataProvider('nonSubmittingHttpMethods')]
    public function doesNotSendTheNewsletterForANonPostRequest(string $httpMethod): void
    {
        // The whole point: no request that can be replayed by navigation may
        // reach the webservice.
        $eventFileRepository = $this->createEventFileRepositoryThatMustNotSend();

        $subject = $this->createSubject($eventFileRepository, $httpMethod, ['send' => 'live']);

        $subject->createAction(self::createStub(NewsletterChannel::class));

        self::assertSame(
            ['error.sendNotConfirmed'],
            $subject->forwardedFlashMessages,
            'A non-POST send request must be answered with the "not confirmed" message.',
        );
    }

    /**
     * The channel argument must default to null so Extbase's argument mapping cannot reject the request before the POST guard runs.
     */
    #[Test]
    public function theChannelArgumentIsOptionalSoTheGuardRunsFirst(): void
    {
        // Extbase maps and validates action arguments *before* it calls the
        // action method. An argument counts as required unless it carries a
        // default value — being nullable is not enough. Without the default,
        // a request lacking the channel died with a RequiredArgumentMissing-
        // Exception before the POST guard above could reject it cleanly.
        $parameter = (new ReflectionMethod(UniversalMessengerController::class, 'createAction'))
            ->getParameters()[0];

        self::assertTrue(
            $parameter->isDefaultValueAvailable(),
            'createAction() must declare a default for the channel, otherwise Extbase throws'
            . ' before the controller can reject the request.',
        );
        self::assertNull($parameter->getDefaultValue());
    }

    /**
     * No channel could be resolved for the submitted UID (deleted, or the field was tampered with).
     */
    #[Test]
    public function createActionRejectsAMissingChannel(): void
    {
        $eventFileRepository = $this->createEventFileRepositoryThatMustNotSend();

        $subject = $this->createSubject(
            $eventFileRepository,
            'POST',
            ['send' => 'live'],
        );

        $subject->createAction();

        self::assertSame(
            ['error.invalidRequest'],
            $subject->forwardedFlashMessages,
        );
    }

    /**
     * A crafted POST that carries a valid channel but omits the "send" argument (e.g. the submit button's name/value).
     */
    #[Test]
    public function createActionRejectsAMissingSendArgument(): void
    {
        $eventFileRepository = $this->createEventFileRepositoryThatMustNotSend();

        $subject = $this->createSubject(
            $eventFileRepository,
            'POST',
            [],
        );

        $subject->createAction(self::createStub(NewsletterChannel::class));

        self::assertSame(
            ['error.invalidRequest'],
            $subject->forwardedFlashMessages,
        );
    }

    /**
     * Proves createAction() actually calls the authorization guard and
     * collapses its result into the generic message, not just that the guard
     * itself discriminates correctly in isolation (the direct
     * getChannelAuthorizationFailure() tests below cover that exhaustively).
     *
     * Uses a hidden page, not a channel mismatch: the guard's own reason for
     * a hidden page is 'error.pageHidden', so this is the one scenario able
     * to prove createAction() collapses that specific reason into the
     * generic 'error.accessNotAllowed' instead of leaking it verbatim to a
     * crafted POST. A channel-mismatch scenario would pass this assertion
     * even if createAction() forwarded the guard's result as-is, because
     * that specific reason already happens to be 'error.accessNotAllowed'.
     *
     * If this guard were ever removed entirely, createAction() would proceed
     * past it with newsletterUrlOverride left unset, so getNewsletterUrl()
     * (doubled in TestableUniversalMessengerController) would return the
     * empty string, isUrlValid('') would fail, and the assertion below would
     * turn red on a plain 'error.noSiteConfiguration' mismatch instead of
     * 'error.accessNotAllowed', not via an incidental collaborator error.
     */
    #[Test]
    public function createActionRejectsAnUnauthorizedSendBeforeTouchingTheWebserviceAndCollapsesTheReason(): void
    {
        $eventFileRepository = $this->createEventFileRepositoryThatMustNotSend();

        $subject = $this->createSubject(
            $eventFileRepository,
            'POST',
            ['send' => 'live'],
        );

        $this->authorizeSubjectForCreateAction(
            $subject,
            [self::CONFIGURED_CHANNEL_UID],
            ['hidden' => 1],
        );

        $newsletterChannel = $this->createNewsletterChannelStub(self::CONFIGURED_CHANNEL_UID);

        $subject->createAction($newsletterChannel);

        self::assertSame(
            ['error.accessNotAllowed'],
            $subject->forwardedFlashMessages,
            'createAction() must answer with the generic message, not the guard\'s specific "error.pageHidden" reason.',
        );
    }

    /**
     * The actual GitHub #139 regression test: proves createAction() wires the
     * *submitted* channel UID into the guard, not the page's own configured
     * channel. Every other test covering the channel-mismatch branch calls
     * getChannelAuthorizationFailure() directly with an explicit UID and
     * therefore cannot tell "createAction() forwards what was submitted"
     * apart from "createAction() forwards the page's own channel" (both
     * would authorize a legitimate send; only the former closes the IDOR).
     * A page correctly configured and permitted for CONFIGURED_CHANNEL_UID
     * must still reject a POST that submits a different channel.
     *
     * A mutant that wires the page's own channel instead of the submitted
     * one is caught the same way as the sibling wiring test above: execution
     * proceeds past the guard, getNewsletterUrl() (doubled in
     * TestableUniversalMessengerController) returns the empty string with
     * newsletterUrlOverride left unset, and the assertion below turns red on
     * 'error.noSiteConfiguration' instead of 'error.accessNotAllowed'.
     */
    #[Test]
    public function createActionRejectsWhenTheSubmittedChannelIsWiredInsteadOfThePagesOwnChannel(): void
    {
        $eventFileRepository = $this->createEventFileRepositoryThatMustNotSend();

        $submittedChannelUid = self::CONFIGURED_CHANNEL_UID + 94;

        $subject = $this->createSubject(
            $eventFileRepository,
            'POST',
            ['send' => 'live'],
        );

        $this->authorizeSubjectForCreateAction(
            $subject,
            [self::CONFIGURED_CHANNEL_UID, $submittedChannelUid],
        );

        $newsletterChannel = $this->createNewsletterChannelStub($submittedChannelUid);

        $subject->createAction($newsletterChannel);

        self::assertSame(
            ['error.accessNotAllowed'],
            $subject->forwardedFlashMessages,
            "createAction() must reject a channel that does not match the page's configured channel,"
            . " even though both the page's channel and the submitted channel are individually permitted.",
        );
    }

    /**
     * Positive control: every other createAction() rejection test above sets
     * up a scenario the guard is *supposed* to reject, so none of them can
     * tell a real guard apart from one that rejects unconditionally. This
     * proves the opposite: a fully authorized request (valid page, matching
     * channel, permitted user) is not rejected by the guard and reaches the
     * webservice. The deeper request-building assertions (channel suffix,
     * tag, subject prefix) belong to the two dedicated tests below, which
     * this one deliberately leaves out to stay focused on the guard alone.
     */
    #[Test]
    public function createActionProceedsPastTheGuardWhenThePageAndPermissionAreValid(): void
    {
        $eventFileRepository = $this->createEventFileRepositoryThatSucceeds();

        $subject = $this->createSubjectPastTheGuard(
            $eventFileRepository,
            'live',
        );

        $subject->createAction($this->createNewsletterChannelStub(self::CONFIGURED_CHANNEL_UID));

        self::assertSame(
            [],
            $subject->forwardedFlashMessages,
            'createAction() must not reject an authorized request.',
        );
    }

    /**
     * Both variants of an invalid newsletter URL that must be rejected
     * before any collaborator further down the chain is touched.
     *
     * @return array<string, string[]>
     */
    public static function invalidNewsletterUrlDataProvider(): array
    {
        return [
            'Empty URL'     => [''],
            'Malformed URL' => ['not a url'],
        ];
    }

    /**
     * An invalid newsletter URL, whether empty or malformed, must be
     * rejected before any collaborator further down the chain is touched.
     */
    #[Test]
    #[DataProvider('invalidNewsletterUrlDataProvider')]
    public function createActionRejectsWithNoSiteConfigurationWhenTheNewsletterUrlIsInvalid(string $invalidUrl): void
    {
        $eventFileRepository = $this->createEventFileRepositoryThatMustNotSend();

        $subject = $this->createSubject(
            $eventFileRepository,
            'POST',
            ['send' => 'live'],
        );

        $this->authorizeSubjectForCreateAction(
            $subject,
            [self::CONFIGURED_CHANNEL_UID],
        );
        $subject->newsletterUrlOverride = $invalidUrl;

        $subject->createAction($this->createNewsletterChannelStub(self::CONFIGURED_CHANNEL_UID));

        self::assertSame(
            ['error.noSiteConfiguration'],
            $subject->forwardedFlashMessages,
        );
    }

    /**
     * The site the page belongs to disappeared (deleted/misconfigured)
     * between form render and submit.
     */
    #[Test]
    public function createActionRejectsWithNoSiteConfigurationWhenSiteFinderThrows(): void
    {
        $eventFileRepository = $this->createEventFileRepositoryThatMustNotSend();

        $subject = $this->createSubject(
            $eventFileRepository,
            'POST',
            ['send' => 'live'],
        );

        $this->authorizeSubjectForCreateAction(
            $subject,
            [self::CONFIGURED_CHANNEL_UID],
        );
        $subject->newsletterUrlOverride = 'https://example.org/newsletter';

        $siteFinder = self::createStub(SiteFinder::class);
        $siteFinder
            ->method('getSiteByPageId')
            ->willThrowException(new SiteNotFoundException('No site found for the given page.'));
        $this->injectProperty(
            $subject,
            'siteFinder',
            $siteFinder,
        );

        $subject->createAction($this->createNewsletterChannelStub(self::CONFIGURED_CHANNEL_UID));

        self::assertSame(
            ['error.noSiteConfiguration'],
            $subject->forwardedFlashMessages,
        );
    }

    /**
     * The catch block above is written as `catch (Exception)`, not
     * `catch (SiteNotFoundException)`: proves it stays generic by throwing a
     * different, unrelated exception type from a different collaborator in
     * the same try block, not just the one SiteNotFoundException the
     * sibling test above already covers.
     */
    #[Test]
    public function createActionRejectsWithNoSiteConfigurationWhenNewsletterRenderServiceThrows(): void
    {
        $eventFileRepository = $this->createEventFileRepositoryThatMustNotSend();

        $subject = $this->createSubject(
            $eventFileRepository,
            'POST',
            ['send' => 'live'],
        );

        $this->authorizeSubjectForCreateAction(
            $subject,
            [self::CONFIGURED_CHANNEL_UID],
        );
        $subject->newsletterUrlOverride = 'https://example.org/newsletter';

        $this->injectProperty(
            $subject,
            'siteFinder',
            $this->createSiteFinderStub(),
        );

        $newsletterRenderService = self::createStub(NewsletterRenderService::class);
        $newsletterRenderService
            ->method('renderNewsletterPage')
            ->willThrowException(new RuntimeException('Rendering the newsletter page failed.'));
        $this->injectProperty(
            $subject,
            'newsletterRenderService',
            $newsletterRenderService,
        );

        $subject->createAction($this->createNewsletterChannelStub(self::CONFIGURED_CHANNEL_UID));

        self::assertSame(
            ['error.noSiteConfiguration'],
            $subject->forwardedFlashMessages,
        );
    }

    /**
     * Proves createAction() builds the TEST request with the TEST channel
     * suffix, the "TEST" tag and the "TEST: " subject prefix, and that an
     * exception raised while sending it is reported via the generic
     * "exceptionDuringCreate" message and logged, instead of propagating.
     * See the class docblock for why the mock throwing is also what makes
     * this scenario observable at all in a unit test.
     */
    #[Test]
    public function createActionBuildsTheTestRequestAndReportsAWebserviceException(): void
    {
        $newsletterChannel = $this->createNewsletterChannelStub(
            self::CONFIGURED_CHANNEL_UID,
            'crmDemoChannel',
            skipUsedId: true,
            title: 'Camino CRM Demo Channel',
        );

        $serviceException = new ServiceException('The webservice is unavailable.');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'The webservice is unavailable.',
                self::callback(static fn (array $context): bool => ($context['exception'] ?? null) === $serviceException),
            );

        $eventFileRepository = $this->createMock(EventFileRepository::class);
        $eventFileRepository
            ->expects($this->once())
            ->method('sendEventFile')
            ->with(self::callback(function (Event $event): bool {
                $this->assertBuiltRequest(
                    $event,
                    'crmDemoChannel' . self::TEST_CHANNEL_SUFFIX,
                    'TEST',
                    'Camino CRM Demo Channel',
                );
                self::assertSame(
                    'TEST: Camino Demo Newsletter',
                    $this->emailSubjectOf($event),
                );
                self::assertNull(
                    $event->getId(),
                    'a TEST send must not carry a generated live event ID',
                );
                self::assertFalse(
                    $event->getSkipUsedIDs(),
                    'skipUsedIDs only ever applies to a LIVE send',
                );

                return true;
            }))
            ->willThrowException($serviceException);

        $subject = $this->createSubjectPastTheGuard(
            $eventFileRepository,
            'test',
        );

        $subject->setLogger($logger);

        $subject->createAction($newsletterChannel);

        self::assertSame(
            ['error.exceptionDuringCreate'],
            $subject->forwardedFlashMessages,
        );
    }

    /**
     * The catch block above is written as `catch (Exception $exception)`,
     * not `catch (ServiceException $exception)`: proves it stays generic by
     * having the webservice call throw a different, unrelated exception
     * type than the ServiceException the sibling test above already covers.
     */
    #[Test]
    public function createActionReportsAGenericExceptionDuringTheWebserviceCall(): void
    {
        $runtimeException = new RuntimeException('Something unrelated to the webservice failed.');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Something unrelated to the webservice failed.',
                self::callback(static fn (array $context): bool => ($context['exception'] ?? null) === $runtimeException),
            );

        $eventFileRepository = $this->createMock(EventFileRepository::class);
        $eventFileRepository
            ->expects($this->once())
            ->method('sendEventFile')
            ->willThrowException($runtimeException);

        $subject = $this->createSubjectPastTheGuard(
            $eventFileRepository,
            'test',
        );

        $subject->setLogger($logger);

        $subject->createAction($this->createNewsletterChannelStub(self::CONFIGURED_CHANNEL_UID));

        self::assertSame(
            ['error.exceptionDuringCreate'],
            $subject->forwardedFlashMessages,
        );
    }

    /**
     * The catch block above calls `$this->logger?->error(...)`, not
     * `$this->logger->error(...)`: proves the nullsafe operator is
     * load-bearing by deliberately never calling setLogger(), leaving the
     * inherited LoggerAwareTrait default (null) in place, and confirming
     * createAction() still reports the error gracefully instead of fataling
     * on a call to a method on null.
     */
    #[Test]
    public function createActionReportsAWebserviceExceptionGracefullyWithoutALogger(): void
    {
        $eventFileRepository = $this->createMock(EventFileRepository::class);
        $eventFileRepository
            ->expects($this->once())
            ->method('sendEventFile')
            ->willThrowException(new RuntimeException('Something unrelated to the webservice failed.'));

        $subject = $this->createSubjectPastTheGuard(
            $eventFileRepository,
            'test',
        );

        $subject->createAction($this->createNewsletterChannelStub(self::CONFIGURED_CHANNEL_UID));

        self::assertSame(
            ['error.exceptionDuringCreate'],
            $subject->forwardedFlashMessages,
        );
    }

    /**
     * Proves createAction() builds the LIVE request with the LIVE channel
     * suffix, the "LIVE" tag and an un-prefixed subject, and that a
     * successful LIVE send forwards no flash message at all (unlike the TEST
     * success path, LIVE never touches moduleTemplate), returning straight
     * to the index view.
     */
    #[Test]
    public function createActionBuildsTheLiveRequestAndSucceedsWithoutForwardingAMessage(): void
    {
        $newsletterChannel = $this->createNewsletterChannelStub(
            self::CONFIGURED_CHANNEL_UID,
            'crmDemoChannel',
            'newsletter@example.org',
            'support@example.org',
            skipUsedId: true,
            embedImages: 'all',
            title: 'Camino CRM Demo Channel',
        );

        $eventFileRepository = $this->createMock(EventFileRepository::class);
        $eventFileRepository
            ->expects($this->once())
            ->method('sendEventFile')
            ->with(self::callback(function (Event $event): bool {
                $this->assertBuiltRequest(
                    $event,
                    'crmDemoChannel' . self::LIVE_CHANNEL_SUFFIX,
                    'LIVE',
                    'Camino CRM Demo Channel',
                    expectedSender: 'newsletter@example.org',
                    expectedReplyTo: 'support@example.org',
                );
                self::assertSame(
                    'Camino Demo Newsletter',
                    $this->emailSubjectOf($event),
                );
                self::assertSame(
                    'LIVE-CAMINO-EN-0',
                    $event->getId(),
                    'the generated live event ID must be attached to a LIVE send',
                );
                self::assertTrue(
                    $event->getSkipUsedIDs(),
                    "the channel's isSkipUsedId() must be forwarded on a LIVE send",
                );

                $htmltext = $this->emailOf($event)->getHtmltext();
                self::assertInstanceOf(
                    HtmlText::class,
                    $htmltext,
                );
                self::assertSame(
                    'all',
                    $htmltext->getEmbedImages(),
                );

                return true;
            }))
            ->willReturn(true);

        $subject = $this->createSubjectPastTheGuard(
            $eventFileRepository,
            'live',
        );

        $response = $subject->createAction($newsletterChannel);

        self::assertSame(
            [],
            $subject->forwardedFlashMessages,
        );
        self::assertSame(
            [],
            $subject->addedFlashMessages,
            'a LIVE send must not add the TEST-only hold status message.',
        );
        self::assertInstanceOf(
            ForwardResponse::class,
            $response,
        );
        self::assertSame(
            'index',
            $response->getActionName(),
        );
    }

    /**
     * Proves the TEST-success flash message is added via the same
     * addModuleFlashMessage() helper the rejection paths use, not a direct,
     * untestable moduleTemplate call, and with the INFO severity a
     * successful send warrants. The request-building assertions belong to
     * createActionBuildsTheTestRequestAndReportsAWebserviceException()
     * above; this one stays focused on the success outcome.
     */
    #[Test]
    public function createActionSendsATestNewsletterAndAddsTheHoldStatusMessage(): void
    {
        $newsletterChannel = $this->createNewsletterChannelStub(
            self::CONFIGURED_CHANNEL_UID,
            'crmDemoChannel',
        );

        $eventFileRepository = $this->createEventFileRepositoryThatSucceeds();

        $subject = $this->createSubjectPastTheGuard(
            $eventFileRepository,
            'test',
        );

        $subject->createAction($newsletterChannel);

        self::assertSame(
            [],
            $subject->forwardedFlashMessages,
        );
        self::assertSame(
            [
                [
                    'key'      => 'newsletter.status.hold',
                    'severity' => ContextualFeedbackSeverity::INFO,
                ],
            ],
            $subject->addedFlashMessages,
        );
    }

    /**
     * The TEST/LIVE channel suffix extension settings are optional.
     * createAction() must still build a request when neither is configured,
     * using the bare channel ID instead of appending the string "null" or
     * failing.
     */
    #[Test]
    public function createActionBuildsTheChannelIdWithoutASuffixWhenTheSettingIsNotConfigured(): void
    {
        $newsletterChannel = $this->createNewsletterChannelStub(
            self::CONFIGURED_CHANNEL_UID,
            'crmDemoChannel',
            title: 'Camino CRM Demo Channel',
        );

        $eventFileRepository = $this->createMock(EventFileRepository::class);
        $eventFileRepository
            ->expects($this->once())
            ->method('sendEventFile')
            ->with(self::callback(function (Event $event): bool {
                $this->assertBuiltRequest(
                    $event,
                    'crmDemoChannel',
                    'TEST',
                    'Camino CRM Demo Channel',
                );

                return true;
            }))
            ->willReturn(true);

        $subject = $this->createSubjectPastTheGuard(
            $eventFileRepository,
            'test',
            [],
        );

        $subject->createAction($newsletterChannel);

        self::assertSame(
            [],
            $subject->forwardedFlashMessages,
        );
    }

    /**
     * Same as createActionBuildsTheChannelIdWithoutASuffixWhenTheSettingIsNotConfigured()
     * above, but for a LIVE send: every other LIVE-type test relies on
     * createSubjectPastTheGuard()'s default $extensionSettingsMap, which
     * always configures "newsletter/liveChannelSuffix", so the `?? ''`
     * fallback on that specific branch was never exercised with a LIVE send
     * and could be replaced by any other placeholder without a test turning
     * red.
     */
    #[Test]
    public function createActionBuildsTheLiveChannelIdWithoutASuffixWhenTheSettingIsNotConfigured(): void
    {
        $newsletterChannel = $this->createNewsletterChannelStub(
            self::CONFIGURED_CHANNEL_UID,
            'crmDemoChannel',
            title: 'Camino CRM Demo Channel',
        );

        $eventFileRepository = $this->createMock(EventFileRepository::class);
        $eventFileRepository
            ->expects($this->once())
            ->method('sendEventFile')
            ->with(self::callback(function (Event $event): bool {
                $this->assertBuiltRequest(
                    $event,
                    'crmDemoChannel',
                    'LIVE',
                    'Camino CRM Demo Channel',
                );
                self::assertFalse(
                    $event->getSkipUsedIDs(),
                    "the channel's isSkipUsedId() must still be forwarded on a LIVE send, not just assumed true",
                );

                return true;
            }))
            ->willReturn(true);

        $subject = $this->createSubjectPastTheGuard(
            $eventFileRepository,
            'live',
            [],
        );

        $subject->createAction($newsletterChannel);

        self::assertSame(
            [],
            $subject->forwardedFlashMessages,
        );
    }

    /**
     * getPageTitle() prefers a localized page-record translation over the
     * plain page record when one exists, and that translated title must
     * flow into the email subject line createAction() sends, not the
     * untranslated title every other test above uses.
     */
    #[Test]
    public function createActionUsesTheTranslatedPageTitleWhenALocalizationExists(): void
    {
        $translatedPageRecord = self::createStub(RawRecord::class);
        $translatedPageRecord
            ->method('get')
            ->willReturnMap([
                ['title', 'Camino Demo Newsletter (translated)'],
            ]);

        $newsletterChannel = $this->createNewsletterChannelStub(
            self::CONFIGURED_CHANNEL_UID,
            'crmDemoChannel',
            title: 'Camino CRM Demo Channel',
        );

        $eventFileRepository = $this->createMock(EventFileRepository::class);
        $eventFileRepository
            ->expects($this->once())
            ->method('sendEventFile')
            ->with(self::callback(function (Event $event): bool {
                self::assertSame(
                    'Camino Demo Newsletter (translated)',
                    $this->emailSubjectOf($event),
                );

                return true;
            }))
            ->willReturn(true);

        $subject = $this->createSubjectPastTheGuard(
            $eventFileRepository,
            'live',
            translatedPageRecord: $translatedPageRecord,
        );

        $subject->createAction($newsletterChannel);

        self::assertSame(
            [],
            $subject->forwardedFlashMessages,
        );
    }

    /**
     * Page was deleted, or never existed, between form render and submit.
     */
    #[Test]
    public function authorizationFailsWithPageNotAllowedWhenThePageDoesNotExist(): void
    {
        $subject = $this->createGuardSubject(permittedChannelUids: [self::CONFIGURED_CHANNEL_UID]);

        self::assertSame(
            'error.pageNotAllowed',
            $subject->getChannelAuthorizationFailure(null, self::CONFIGURED_CHANNEL_UID),
        );
    }

    /**
     * Page type changed (or was never the newsletter doktype) under the submitted request.
     */
    #[Test]
    public function authorizationFailsWithPageNotAllowedWhenTheDoktypeDoesNotMatch(): void
    {
        $subject    = $this->createGuardSubject(permittedChannelUids: [self::CONFIGURED_CHANNEL_UID]);
        $pageRecord = $this->validNewsletterPageRecord(['doktype' => self::NEWSLETTER_PAGE_DOKTYPE + 1]);

        self::assertSame(
            'error.pageNotAllowed',
            $subject->getChannelAuthorizationFailure($pageRecord, self::CONFIGURED_CHANNEL_UID),
        );
    }

    /**
     * A page record without a "hidden" column at all must reject just as a hidden page does.
     */
    #[Test]
    public function authorizationFailsWithPageHiddenWhenTheHiddenFlagIsMissing(): void
    {
        $subject    = $this->createGuardSubject(permittedChannelUids: [self::CONFIGURED_CHANNEL_UID]);
        $pageRecord = $this->validNewsletterPageRecord();
        unset($pageRecord['hidden']);

        self::assertSame(
            'error.pageHidden',
            $subject->getChannelAuthorizationFailure($pageRecord, self::CONFIGURED_CHANNEL_UID),
        );
    }

    /**
     * Page was hidden after the form was rendered. Covers both the literal
     * TCA-checkbox value and TYPO3's "any non-zero value disables the
     * record" convention for the enable-column, so the check stays
     * `>= 1` and does not collapse to `=== 1`.
     *
     * @return array<string, array{int}>
     */
    public static function hiddenValues(): array
    {
        return [
            'checkbox value'      => [1],
            'non-zero convention' => [2],
        ];
    }

    /**
     * Page was hidden after the form was rendered.
     */
    #[Test]
    #[DataProvider('hiddenValues')]
    public function authorizationFailsWithPageHiddenWhenThePageIsHidden(int $hidden): void
    {
        $subject    = $this->createGuardSubject(permittedChannelUids: [self::CONFIGURED_CHANNEL_UID]);
        $pageRecord = $this->validNewsletterPageRecord(['hidden' => $hidden]);

        self::assertSame(
            'error.pageHidden',
            $subject->getChannelAuthorizationFailure($pageRecord, self::CONFIGURED_CHANNEL_UID),
        );
    }

    /**
     * A page record without a "universal_messenger_channel" column at all must reject just as an unconfigured one does.
     */
    #[Test]
    public function authorizationFailsWithMissingChannelConfigurationWhenTheChannelKeyIsMissing(): void
    {
        $subject    = $this->createGuardSubject(permittedChannelUids: [self::CONFIGURED_CHANNEL_UID]);
        $pageRecord = $this->validNewsletterPageRecord();
        unset($pageRecord['universal_messenger_channel']);

        self::assertSame(
            'error.missingChannelConfiguration',
            $subject->getChannelAuthorizationFailure($pageRecord, self::CONFIGURED_CHANNEL_UID),
        );
    }

    /**
     * The page still carries the field's unconfigured default value, or a
     * corrupted/tampered one. Covers both halves the test's own name
     * promises, so the check stays `<= 0` and does not collapse to `=== 0`.
     *
     * @return array<string, array{int}>
     */
    public static function zeroOrNegativeChannelValues(): array
    {
        return [
            'zero'     => [0],
            'negative' => [-1],
        ];
    }

    /**
     * The page still carries the field's unconfigured default value, or a corrupted/tampered one.
     */
    #[Test]
    #[DataProvider('zeroOrNegativeChannelValues')]
    public function authorizationFailsWithMissingChannelConfigurationWhenTheChannelIsZeroOrNegative(int $channelUid): void
    {
        $subject    = $this->createGuardSubject(permittedChannelUids: [self::CONFIGURED_CHANNEL_UID]);
        $pageRecord = $this->validNewsletterPageRecord(['universal_messenger_channel' => $channelUid]);

        self::assertSame(
            'error.missingChannelConfiguration',
            $subject->getChannelAuthorizationFailure($pageRecord, self::CONFIGURED_CHANNEL_UID),
        );
    }

    /**
     * Pins the (int) cast on this specific comparison. PHP 8's numeric-string
     * coercion already makes "5" <= 0 agree with (int) "5" <= 0, so only a
     * non-numeric string can distinguish the cast from no cast here (unlike
     * the !== comparison a few lines down, where any string differs from an
     * int without the cast).
     */
    #[Test]
    public function authorizationFailsWithMissingChannelConfigurationWhenTheChannelIsANonNumericString(): void
    {
        $subject    = $this->createGuardSubject(permittedChannelUids: [self::CONFIGURED_CHANNEL_UID]);
        $pageRecord = $this->validNewsletterPageRecord(['universal_messenger_channel' => 'abc']);

        self::assertSame(
            'error.missingChannelConfiguration',
            $subject->getChannelAuthorizationFailure($pageRecord, self::CONFIGURED_CHANNEL_UID),
        );
    }

    /**
     * The channel-mismatch check is a strict equality (!==), not a magnitude
     * comparison, so both directions must reject: a submitted UID greater
     * than the page's configured channel, and one smaller than it. Every
     * fixture using only "+94" would leave a mutation to a "<"/">"
     * comparison undetected.
     *
     * @return array<string, array{int}>
     */
    public static function mismatchedChannelUids(): array
    {
        return [
            "greater than the page's configured channel" => [self::CONFIGURED_CHANNEL_UID + 94],
            "smaller than the page's configured channel" => [self::CONFIGURED_CHANNEL_UID - 1],
        ];
    }

    /**
     * The core IDOR case: a channel the user genuinely owns, submitted for a page configured for a different one.
     */
    #[Test]
    #[DataProvider('mismatchedChannelUids')]
    public function authorizationFailsWithAccessNotAllowedWhenTheSubmittedChannelIsNotConfiguredOnTheCurrentPage(int $submittedChannelUid): void
    {
        // The backend user legitimately owns the submitted channel elsewhere,
        // but the current page is configured for a different channel.
        // Submitting it for this page must be rejected even though the user
        // is permitted to use it in general.
        $subject = $this->createGuardSubject(permittedChannelUids: [$submittedChannelUid]);

        self::assertSame(
            'error.accessNotAllowed',
            $subject->getChannelAuthorizationFailure($this->validNewsletterPageRecord(), $submittedChannelUid),
        );
    }

    /**
     * Channel correctly configured on the page, but the current user has no permission for it at all.
     */
    #[Test]
    public function authorizationFailsWithAccessNotAllowedWhenTheCurrentUserHasNoPermissionForTheChannel(): void
    {
        $subject = $this->createGuardSubject(permittedChannelUids: []);

        self::assertSame(
            'error.accessNotAllowed',
            $subject->getChannelAuthorizationFailure($this->validNewsletterPageRecord(), self::CONFIGURED_CHANNEL_UID),
        );
    }

    /**
     * The positive control: proves the guard actually discriminates, not just rejects.
     */
    #[Test]
    public function authorizationSucceedsWhenThePageAndPermissionAreValid(): void
    {
        // Without this test, a guard collapsed to an unconditional rejection
        // would still pass every "fails with ..." test above, because none
        // of them observe anything but rejection.
        $subject = $this->createGuardSubject(permittedChannelUids: [self::CONFIGURED_CHANNEL_UID]);

        self::assertNull(
            $subject->getChannelAuthorizationFailure($this->validNewsletterPageRecord(), self::CONFIGURED_CHANNEL_UID),
        );
    }

    /**
     * Pins the (int) cast on the page's configured channel. BackendUtility::getRecord()
     * returns raw DB column values, which arrive as a numeric string on some
     * platforms/drivers. Without the cast, a strict !== comparison against the
     * submitted int UID would treat a matching channel as a mismatch and reject
     * a legitimate send.
     */
    #[Test]
    public function authorizationSucceedsWhenThePagesConfiguredChannelIsANumericString(): void
    {
        $subject = $this->createGuardSubject(permittedChannelUids: [self::CONFIGURED_CHANNEL_UID]);

        $pageRecord = $this->validNewsletterPageRecord([
            'universal_messenger_channel' => (string) self::CONFIGURED_CHANNEL_UID,
        ]);

        self::assertNull(
            $subject->getChannelAuthorizationFailure($pageRecord, self::CONFIGURED_CHANNEL_UID),
        );
    }

    /**
     * Pins the (int) cast on the page's doktype. BackendUtility::getRecord()
     * returns raw DB column values, which arrive as a numeric string on some
     * platforms/drivers. Without the cast, a strict !== comparison against the
     * configured int doktype would treat a legitimate newsletter page as a
     * mismatch and reject it with error.pageNotAllowed.
     */
    #[Test]
    public function authorizationSucceedsWhenThePagesDoktypeIsANumericString(): void
    {
        $subject = $this->createGuardSubject(permittedChannelUids: [self::CONFIGURED_CHANNEL_UID]);

        $pageRecord = $this->validNewsletterPageRecord([
            'doktype' => (string) self::NEWSLETTER_PAGE_DOKTYPE,
        ]);

        self::assertNull(
            $subject->getChannelAuthorizationFailure($pageRecord, self::CONFIGURED_CHANNEL_UID),
        );
    }

    /**
     * getNewsletterChannelPermissions() merges be_groups permissions into be_users ones; exercise that merge, not just the user-record path every other test above uses.
     */
    #[Test]
    public function authorizationSucceedsWhenThePermissionComesFromABackendGroupRatherThanTheUserRecord(): void
    {
        $subject = $this->createGuardSubject(
            permittedChannelUids: [],
            groupChannelUids: [self::CONFIGURED_CHANNEL_UID],
        );

        self::assertNull(
            $subject->getChannelAuthorizationFailure($this->validNewsletterPageRecord(), self::CONFIGURED_CHANNEL_UID),
        );
    }

    /**
     * Pins that the merge in getNewsletterChannelPermissions() genuinely
     * accumulates both sources rather than the later assignment silently
     * overwriting the earlier one. Every other test leaves at most one
     * source non-empty, so none of them can tell "accumulate" apart from
     * "last writer wins": the user record here carries an unrelated,
     * non-empty channel, and only the group grants the configured one.
     */
    #[Test]
    public function authorizationSucceedsWhenTheGroupPermissionSurvivesAlongsideAnUnrelatedUserPermission(): void
    {
        $unrelatedUserChannelUid = self::CONFIGURED_CHANNEL_UID + 50;

        $subject = $this->createGuardSubject(
            permittedChannelUids: [$unrelatedUserChannelUid],
            groupChannelUids: [self::CONFIGURED_CHANNEL_UID],
        );

        self::assertNull(
            $subject->getChannelAuthorizationFailure($this->validNewsletterPageRecord(), self::CONFIGURED_CHANNEL_UID),
        );
    }

    /**
     * Same accumulate-not-overwrite property as the test above, but across
     * two distinct groups rather than group-vs-user: no fixture puts a
     * backend user in more than one group with a channel set, so nothing
     * previously discriminated accumulating across groups from a
     * last-group-processed-wins mutant.
     */
    #[Test]
    public function authorizationSucceedsWhenThePermissionComesFromASecondGroupRatherThanTheFirst(): void
    {
        $otherGroupChannelUid = self::CONFIGURED_CHANNEL_UID + 60;

        $subject = $this->newTestableController();

        $backendUserAuthentication       = self::createStub(BackendUserAuthentication::class);
        $backendUserAuthentication->user = ['universal_messenger_channels' => ''];
        // The configured channel's group is deliberately first, not last: an
        // overwrite-instead-of-accumulate mutant would leave only the LAST
        // group's channel in the permission list, so ordering it last would
        // let the mutant pass by coincidence.
        $backendUserAuthentication->userGroups = [
            ['universal_messenger_channels' => (string) self::CONFIGURED_CHANNEL_UID],
            ['universal_messenger_channels' => (string) $otherGroupChannelUid],
        ];

        $subject->backendUserAuthenticationOverride = $backendUserAuthentication;

        $this->injectConfigurationStub($subject);

        self::assertNull(
            $subject->getChannelAuthorizationFailure($this->validNewsletterPageRecord(), self::CONFIGURED_CHANNEL_UID),
        );
    }

    /**
     * A missing/wrong-doktype page is informational, not an error the editor needs to act on.
     */
    #[Test]
    public function authorizationFailureSeverityIsInfoForAnUnreachablePage(): void
    {
        self::assertSame(
            ContextualFeedbackSeverity::INFO,
            $this->createSeveritySubject()->getAuthorizationFailureSeverity('error.pageNotAllowed'),
        );
    }

    /**
     * Every other authorization-failure reason is an error, not just the two
     * this test names, so it doubles as the positive control against a
     * severity mapping collapsed to always INFO or always ERROR.
     */
    #[Test]
    public function authorizationFailureSeverityIsErrorForEveryOtherReason(): void
    {
        $subject = $this->createSeveritySubject();

        self::assertSame(
            ContextualFeedbackSeverity::ERROR,
            $subject->getAuthorizationFailureSeverity('error.pageHidden'),
        );
        self::assertSame(
            ContextualFeedbackSeverity::ERROR,
            $subject->getAuthorizationFailureSeverity('error.accessNotAllowed'),
        );
    }

    /**
     * Most collaborators are final in TYPO3 v14 and cannot be doubled, so
     * every subject is built this way and only the collaborators a given
     * test actually needs are injected afterward.
     */
    private function newTestableController(): TestableUniversalMessengerController
    {
        /** @var TestableUniversalMessengerController $subject */
        $subject = (new ReflectionClass(TestableUniversalMessengerController::class))
            ->newInstanceWithoutConstructor();

        return $subject;
    }

    /**
     * getAuthorizationFailureSeverity() is a pure function of its argument, so
     * the subject needs no collaborators at all, unlike createGuardSubject().
     */
    private function createSeveritySubject(): TestableUniversalMessengerController
    {
        return $this->newTestableController();
    }

    /**
     * Builds a controller with only the collaborators
     * getChannelAuthorizationFailure() needs: the configuration (for the
     * doktype) and the backend user (for the permission list).
     *
     * @param int[] $permittedChannelUids
     * @param int[] $groupChannelUids
     */
    private function createGuardSubject(
        array $permittedChannelUids,
        array $groupChannelUids = [],
    ): TestableUniversalMessengerController {
        $subject = $this->newTestableController();

        $subject->backendUserAuthenticationOverride = $this->createBackendUserWithChannelPermissions(
            $permittedChannelUids,
            $groupChannelUids,
        );

        $this->injectConfigurationStub($subject);

        return $subject;
    }

    /**
     * Stubs Configuration so the guard's doktype check has a fixed value to
     * compare against. $extensionSettingsMap additionally stubs
     * getExtensionSetting() for tests that reach past the guard, in the
     * willReturnMap() format (pairs of [path, value]).
     *
     * @param array<int, array{0: string, 1: string}> $extensionSettingsMap
     */
    private function createConfigurationStub(array $extensionSettingsMap = []): Configuration
    {
        $configuration = self::createStub(Configuration::class);
        $configuration
            ->method('getNewsletterPageDokType')
            ->willReturn(self::NEWSLETTER_PAGE_DOKTYPE);

        if ($extensionSettingsMap !== []) {
            $configuration
                ->method('getExtensionSetting')
                ->willReturnMap($extensionSettingsMap);
        }

        return $configuration;
    }

    /**
     * Wires the fixed doktype configuration stub every guard test needs.
     * $extensionSettingsMap additionally stubs getExtensionSetting(), for
     * tests that reach past the guard (see createConfigurationStub()).
     *
     * @param array<int, array{0: string, 1: string}> $extensionSettingsMap
     */
    private function injectConfigurationStub(
        TestableUniversalMessengerController $subject,
        array $extensionSettingsMap = [],
    ): void {
        $this->injectProperty(
            $subject,
            'configuration',
            $this->createConfigurationStub($extensionSettingsMap),
        );
    }

    /**
     * A page record that passes every check on its own: the newsletter doktype,
     * visible, configured for channel self::CONFIGURED_CHANNEL_UID.
     *
     * @param array<string, int|string|null> $overrides
     *
     * @return array<string, int|string|null>
     */
    private function validNewsletterPageRecord(array $overrides = []): array
    {
        return array_replace(
            [
                'doktype'                     => self::NEWSLETTER_PAGE_DOKTYPE,
                'hidden'                      => 0,
                'universal_messenger_channel' => self::CONFIGURED_CHANNEL_UID,
            ],
            $overrides,
        );
    }

    /**
     * @param int[] $userChannelUids  Channels permitted directly on the be_users record
     * @param int[] $groupChannelUids Channels permitted via a single be_groups record
     */
    private function createBackendUserWithChannelPermissions(
        array $userChannelUids,
        array $groupChannelUids = [],
    ): BackendUserAuthentication {
        $backendUserAuthentication = self::createStub(BackendUserAuthentication::class);

        $backendUserAuthentication->userGroups = ($groupChannelUids === [])
            ? []
            : [
                ['universal_messenger_channels' => implode(',', $groupChannelUids)],
            ];
        $backendUserAuthentication->user = [
            'universal_messenger_channels' => implode(',', $userChannelUids),
        ];

        return $backendUserAuthentication;
    }

    /**
     * A repository mock that fails the test if the webservice is ever touched.
     * Shared by every createAction() rejection test: none of them may
     * progress far enough to attempt a send.
     */
    private function createEventFileRepositoryThatMustNotSend(): EventFileRepository
    {
        $eventFileRepository = $this->createMock(EventFileRepository::class);
        $eventFileRepository
            ->expects(self::never())
            ->method('sendEventFile');

        return $eventFileRepository;
    }

    /**
     * A repository mock whose sendEventFile() succeeds exactly once, for
     * tests that only care that createAction() reached the webservice, not
     * what request it built.
     */
    private function createEventFileRepositoryThatSucceeds(): EventFileRepository
    {
        $eventFileRepository = $this->createMock(EventFileRepository::class);
        $eventFileRepository
            ->expects($this->once())
            ->method('sendEventFile')
            ->willReturn(true);

        return $eventFileRepository;
    }

    /**
     * A channel stub whose getUid() resolves to a fixed value, standing in for
     * the submitted "newsletterChannel" hidden field. $channelId, $sender,
     * $replyTo, $skipUsedId, $embedImages and $title are only needed by the
     * tests that inspect the request built from them; every other test
     * leaves them at the stub defaults.
     */
    private function createNewsletterChannelStub(
        int $uid,
        string $channelId = '',
        string $sender = '',
        string $replyTo = '',
        bool $skipUsedId = false,
        string $embedImages = '',
        string $title = '',
    ): NewsletterChannel {
        $newsletterChannel = self::createStub(NewsletterChannel::class);
        $newsletterChannel
            ->method('getUid')
            ->willReturn($uid);
        $newsletterChannel
            ->method('getChannelId')
            ->willReturn($channelId);
        $newsletterChannel
            ->method('getSender')
            ->willReturn($sender);
        $newsletterChannel
            ->method('getReplyTo')
            ->willReturn($replyTo);
        $newsletterChannel
            ->method('isSkipUsedId')
            ->willReturn($skipUsedId);
        $newsletterChannel
            ->method('getEmbedImages')
            ->willReturn($embedImages);
        $newsletterChannel
            ->method('getTitle')
            ->willReturn($title);

        return $newsletterChannel;
    }

    /**
     * Builds a subject wired so createAction() can reach past the
     * authorization guard and the webservice request-building logic, for the
     * given send type ("test" or "live"). Only the webservice call itself
     * (the injected $eventFileRepository) is left for the caller to double,
     * since that is the seam every test using this needs to observe.
     * $extensionSettingsMap defaults to both channel suffixes configured;
     * pass an empty array to prove createAction() also works when neither
     * is configured.
     *
     * @param array<int, array{0: string, 1: string}> $extensionSettingsMap
     */
    private function createSubjectPastTheGuard(
        EventFileRepository $eventFileRepository,
        string $sendType,
        array $extensionSettingsMap = [
            ['newsletter/testChannelSuffix', self::TEST_CHANNEL_SUFFIX],
            ['newsletter/liveChannelSuffix', self::LIVE_CHANNEL_SUFFIX],
        ],
        ?RawRecord $translatedPageRecord = null,
    ): TestableUniversalMessengerController {
        $subject = $this->createSubject(
            $eventFileRepository,
            'POST',
            ['send' => $sendType],
        );

        $this->authorizeSubjectForCreateAction(
            $subject,
            [self::CONFIGURED_CHANNEL_UID],
            ['title' => 'Camino Demo Newsletter'],
            $extensionSettingsMap,
        );
        $subject->newsletterUrlOverride = 'https://example.org/newsletter';

        $localizationRepository = self::createStub(LocalizationRepository::class);

        if ($translatedPageRecord instanceof RawRecord) {
            $localizationRepository
                ->method('getRecordTranslation')
                ->willReturn($translatedPageRecord);
        }

        $this->injectProperty(
            $subject,
            'localizationRepository',
            $localizationRepository,
        );
        $this->injectProperty(
            $subject,
            'siteFinder',
            $this->createSiteFinderStub(),
        );
        $this->injectProperty(
            $subject,
            'newsletterRenderService',
            $this->createNewsletterRenderServiceStub(),
        );
        $this->injectProperty(
            $subject,
            'currentSelectedLanguage',
            0,
        );

        return $subject;
    }

    /**
     * A SiteFinder double resolving any page ID to the same doubled Site,
     * used for both getBase() and generateLiveEventId().
     */
    private function createSiteFinderStub(): SiteFinder
    {
        $site = self::createStub(Site::class);
        $site->method('getBase')->willReturn(new Uri('https://example.org/'));
        $site->method('getIdentifier')->willReturn('camino');

        $siteFinder = self::createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        return $siteFinder;
    }

    /**
     * Stands in for the real HTTP self-request that would render the
     * newsletter page.
     */
    private function createNewsletterRenderServiceStub(): NewsletterRenderService
    {
        $newsletterRenderService = self::createStub(NewsletterRenderService::class);
        $newsletterRenderService
            ->method('renderNewsletterPage')
            ->willReturn('<html>Rendered newsletter content</html>');

        return $newsletterRenderService;
    }

    /**
     * Asserts the channel, tags, email settings and HTML body createAction()
     * attached to the built event request. The base/download URL and
     * rendered body content come from the fixed siteFinder/
     * newsletterRenderService stubs createSubjectPastTheGuard() wires up, so
     * every caller can assert against the same literals. $expectedSender and
     * $expectedReplyTo default to null, matching the empty-string-to-null
     * fallback every caller but the LIVE-request test relies on.
     */
    private function assertBuiltRequest(
        Event $event,
        string $expectedChannel,
        string $expectedTag,
        string $expectedTitleTag,
        ?string $expectedSender = null,
        ?string $expectedReplyTo = null,
    ): void {
        $destination = $event->getDestination();

        self::assertInstanceOf(
            Destination::class,
            $destination,
        );
        self::assertSame(
            [$expectedChannel],
            $destination->getChannels(),
        );
        self::assertContains(
            $expectedTag,
            $event->getTags(),
        );
        self::assertContains(
            $expectedTitleTag,
            $event->getTags(),
        );
        self::assertNull($event->getNewsletterGroup());

        $email = $this->emailOf($event);

        self::assertSame(
            'https://example.org/',
            $email->getBaseUrl(),
        );
        self::assertSame(
            'https://example.org/',
            $email->getDownloadUrl(),
        );
        self::assertFalse($email->getObeyPreferHtml());
        self::assertTrue($email->getSendBothParts());
        self::assertSame(
            $expectedSender,
            $email->getSender(),
        );
        self::assertSame(
            $expectedReplyTo,
            $email->getReplyto(),
        );

        $htmltext = $email->getHtmltext();

        self::assertInstanceOf(
            HtmlText::class,
            $htmltext,
        );
        self::assertNull($htmltext->getBaseUrl());
        self::assertSame(
            'https://example.org/',
            $htmltext->getDownloadUrl(),
        );
        self::assertNull($htmltext->getRestProxyUrl());
        self::assertSame(
            'UTF-8',
            $htmltext->getCharset(),
        );
        self::assertTrue($htmltext->getInline());
        self::assertSame(
            '<html>Rendered newsletter content</html>',
            $htmltext->getContent(),
        );
        self::assertFalse($htmltext->getLinkTracking());
        self::assertFalse($htmltext->getViewTracking());
    }

    /**
     * Reads the email createAction() attached to the built event request.
     */
    private function emailOf(Event $event): Email
    {
        $data = $event->getData();

        self::assertInstanceOf(
            Data::class,
            $data,
        );

        $email = $data->getEmail();

        self::assertInstanceOf(
            Email::class,
            $email,
        );

        return $email;
    }

    /**
     * Reads the subject line createAction() attached to the built event
     * request.
     */
    private function emailSubjectOf(Event $event): string
    {
        return (string) $this->emailOf($event)->getSubject();
    }

    /**
     * Layers the authorization collaborators onto a subject already built by
     * createSubject(), mirroring createGuardSubject() for tests that exercise
     * the full createAction() rather than the guard directly.
     *
     * @param TestableUniversalMessengerController    $subject              The controller under test to wire the collaborators onto
     * @param int[]                                   $permittedChannelUids
     * @param array<string, int|string|null>          $pageRecordOverrides
     * @param array<int, array{0: string, 1: string}> $extensionSettingsMap Forwarded to createConfigurationStub(), for tests that reach past the guard
     */
    private function authorizeSubjectForCreateAction(
        TestableUniversalMessengerController $subject,
        array $permittedChannelUids,
        array $pageRecordOverrides = [],
        array $extensionSettingsMap = [],
    ): void {
        $subject->pageRecordOverride                = $this->validNewsletterPageRecord($pageRecordOverrides);
        $subject->backendUserAuthenticationOverride = $this->createBackendUserWithChannelPermissions(
            $permittedChannelUids,
        );

        $this->injectConfigurationStub(
            $subject,
            $extensionSettingsMap,
        );
    }

    /**
     * Builds the controller without running its constructor.
     *
     * Most collaborators are final in TYPO3 v14 and cannot be doubled, and the
     * guard under test must reject the request before any of them is touched —
     * so only the two properties the guard and the assertion need are injected.
     *
     * @param array<string, string> $arguments
     */
    private function createSubject(
        EventFileRepository $eventFileRepository,
        string $httpMethod,
        array $arguments,
    ): TestableUniversalMessengerController {
        $subject = $this->newTestableController();

        $this->injectProperty($subject, 'eventFileRepository', $eventFileRepository);
        $this->injectProperty($subject, 'request', $this->createRequest($httpMethod, $arguments));

        return $subject;
    }

    /**
     * @param array<string, string> $arguments
     */
    private function createRequest(string $httpMethod, array $arguments): RequestInterface
    {
        $request = self::createStub(RequestInterface::class);
        $request
            ->method('getMethod')
            ->willReturn($httpMethod);
        $request
            ->method('hasArgument')
            ->willReturnCallback(
                static fn (string $name): bool => isset($arguments[$name]),
            );
        $request
            ->method('getArgument')
            ->willReturnCallback(
                static fn (string $name): string => $arguments[$name] ?? '',
            );

        return $request;
    }

    /**
     * Every property injected this way is resolved against the controller itself,
     * covering both inherited/protected properties (e.g. "request", "configuration")
     * and ones declared directly on it (e.g. "eventFileRepository"), object
     * collaborators and the one plain scalar property that needs it
     * ("currentSelectedLanguage").
     *
     * The property is re-resolved against its actual declaring class:
     * initializing a readonly property via reflection (e.g. the inherited
     * "localizationRepository") requires that exact class, not merely one
     * that inherits the property, or PHP rejects it as a foreign scope.
     */
    private function injectProperty(object $subject, string $name, object|int $value): void
    {
        $property = new ReflectionProperty(UniversalMessengerController::class, $name);
        $property = new ReflectionProperty($property->getDeclaringClass()->getName(), $name);

        $property->setValue($subject, $value);
    }
}
