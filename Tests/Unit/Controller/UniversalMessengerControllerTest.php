<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit\Controller;

use Error;
use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Controller\UniversalMessengerController;
use Netresearch\UniversalMessenger\Domain\Model\NewsletterChannel;
use Netresearch\UniversalMessenger\Repository\EventFileRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
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

    /** A replayable GET/HEAD/DELETE carrying send parameters must not trigger a live send. */
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

    /** The channel argument must default to null so Extbase's argument mapping cannot reject the request before the POST guard runs. */
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

    /** No channel could be resolved for the submitted UID (deleted, or the field was tampered with). */
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

    /** A crafted POST that carries a valid channel but omits the "send" argument (e.g. the submit button's name/value). */
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
     * Caveat: if this guard were ever removed entirely, this test would still
     * turn red today, but via an unrelated uncaught error several lines
     * further down (getNewsletterUrl() reaching an unbootstrapped TYPO3 core
     * collaborator in this unit-test harness), not via the assertion below.
     * That is incidental to the current call chain, not something this test
     * controls, so it is not evidence this test can rely on if that call
     * chain ever changes.
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
     * Caveat: on an unmutated controller this reaches its own assertion
     * cleanly. But because the page here is otherwise fully valid, a mutant
     * that wires the page's own channel instead of the submitted one is
     * caught the same way as the sibling wiring test above: execution
     * proceeds past the guard and dies with an unrelated uncaught error
     * further down (getNewsletterUrl() reaching an unbootstrapped TYPO3 core
     * collaborator), not via the assertion below. See that test's docblock
     * for why this is not something either test controls.
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
     * Positive control: every other createAction() test sets up a scenario the
     * guard is *supposed* to reject, so none of them can tell a real guard
     * apart from one that rejects unconditionally. This proves the opposite:
     * a fully authorized request (valid page, matching channel, permitted
     * user) is not rejected by the guard.
     *
     * createAction() has no unit-testable success path: the next step,
     * getNewsletterUrl(), reaches TYPO3's PreviewUriBuilder/TcaSchemaFactory,
     * unavailable in this unit-test harness. So this asserts the only thing a
     * unit test can prove here: no rejection flash message was forwarded
     * before execution died on that unrelated, expected collaborator error
     * further down the call chain. That incidental failure mode is not
     * something this test controls.
     *
     * The catch is narrowed to \Error: getNewsletterUrl() dies with an
     * ArgumentCountError, not an \Exception, so a guard that crashes instead
     * of authorizing (any \Exception) still fails this test.
     */
    #[Test]
    public function createActionProceedsPastTheGuardWhenThePageAndPermissionAreValid(): void
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

        $newsletterChannel = $this->createNewsletterChannelStub(self::CONFIGURED_CHANNEL_UID);

        try {
            $subject->createAction($newsletterChannel);
        } catch (Throwable $throwable) {
            // Expected: see the docblock above.
            self::assertInstanceOf(
                Error::class,
                $throwable,
                'createAction() must not throw before/inside the guard;'
                . ' only the unbootstrapped collaborator further down may.',
            );
        }

        self::assertSame(
            [],
            $subject->forwardedFlashMessages,
            'createAction() must not reject an authorized request.',
        );
    }

    /** Page was deleted, or never existed, between form render and submit. */
    #[Test]
    public function authorizationFailsWithPageNotAllowedWhenThePageDoesNotExist(): void
    {
        $subject = $this->createGuardSubject(permittedChannelUids: [self::CONFIGURED_CHANNEL_UID]);

        self::assertSame(
            'error.pageNotAllowed',
            $subject->getChannelAuthorizationFailure(null, self::CONFIGURED_CHANNEL_UID),
        );
    }

    /** Page type changed (or was never the newsletter doktype) under the submitted request. */
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

    /** A page record without a "hidden" column at all must reject just as a hidden page does. */
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

    /** Page was hidden after the form was rendered. */
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

    /** A page record without a "universal_messenger_channel" column at all must reject just as an unconfigured one does. */
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

    /** The page still carries the field's unconfigured default value, or a corrupted/tampered one. */
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

    /** The core IDOR case: a channel the user genuinely owns, submitted for a page configured for a different one. */
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

    /** Channel correctly configured on the page, but the current user has no permission for it at all. */
    #[Test]
    public function authorizationFailsWithAccessNotAllowedWhenTheCurrentUserHasNoPermissionForTheChannel(): void
    {
        $subject = $this->createGuardSubject(permittedChannelUids: []);

        self::assertSame(
            'error.accessNotAllowed',
            $subject->getChannelAuthorizationFailure($this->validNewsletterPageRecord(), self::CONFIGURED_CHANNEL_UID),
        );
    }

    /** The positive control: proves the guard actually discriminates, not just rejects. */
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

    /** getNewsletterChannelPermissions() merges be_groups permissions into be_users ones; exercise that merge, not just the user-record path every other test above uses. */
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

    /** A missing/wrong-doktype page is informational, not an error the editor needs to act on. */
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

    /** Stubs Configuration so the guard's doktype check has a fixed value to compare against. */
    private function createConfigurationStub(): Configuration
    {
        $configuration = self::createStub(Configuration::class);
        $configuration
            ->method('getNewsletterPageDokType')
            ->willReturn(self::NEWSLETTER_PAGE_DOKTYPE);

        return $configuration;
    }

    /** Wires the fixed doktype configuration stub every guard test needs. */
    private function injectConfigurationStub(TestableUniversalMessengerController $subject): void
    {
        $this->injectProperty(
            $subject,
            'configuration',
            $this->createConfigurationStub(),
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

    /** A channel stub whose getUid() resolves to a fixed value, standing in for the submitted "newsletterChannel" hidden field. */
    private function createNewsletterChannelStub(int $uid): NewsletterChannel
    {
        $newsletterChannel = self::createStub(NewsletterChannel::class);
        $newsletterChannel
            ->method('getUid')
            ->willReturn($uid);

        return $newsletterChannel;
    }

    /**
     * Layers the authorization collaborators onto a subject already built by
     * createSubject(), mirroring createGuardSubject() for tests that exercise
     * the full createAction() rather than the guard directly.
     *
     * @param TestableUniversalMessengerController $subject              The controller under test to wire the collaborators onto
     * @param int[]                                $permittedChannelUids
     * @param array<string, int|string|null>       $pageRecordOverrides
     */
    private function authorizeSubjectForCreateAction(
        TestableUniversalMessengerController $subject,
        array $permittedChannelUids,
        array $pageRecordOverrides = [],
    ): void {
        $subject->pageRecordOverride                = $this->validNewsletterPageRecord($pageRecordOverrides);
        $subject->backendUserAuthenticationOverride = $this->createBackendUserWithChannelPermissions(
            $permittedChannelUids,
        );

        $this->injectConfigurationStub($subject);
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
     * and ones declared directly on it (e.g. "eventFileRepository").
     */
    private function injectProperty(object $subject, string $name, object $value): void
    {
        $property = new ReflectionProperty(UniversalMessengerController::class, $name);

        $property->setValue($subject, $value);
    }
}
