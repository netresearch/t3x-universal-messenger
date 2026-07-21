<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit\Controller;

use Netresearch\UniversalMessenger\Controller\UniversalMessengerController;
use Netresearch\UniversalMessenger\Domain\Model\NewsletterChannel;
use Netresearch\UniversalMessenger\Repository\EventFileRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests that sending a newsletter cannot be triggered by a replayable request.
 *
 * A live send is irreversible. It must never be reachable by navigation alone —
 * neither by a bookmark or reload, nor by TYPO3 replaying a pending action after
 * the editor logged in again.
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

    #[Test]
    #[DataProvider('nonSubmittingHttpMethods')]
    public function doesNotSendTheNewsletterForANonPostRequest(string $httpMethod): void
    {
        $eventFileRepository = $this->createMock(EventFileRepository::class);

        // The whole point: no request that can be replayed by navigation may
        // reach the webservice.
        $eventFileRepository
            ->expects(self::never())
            ->method('sendEventFile');

        $subject = $this->createSubject($eventFileRepository, $httpMethod, ['send' => 'live']);

        $subject->createAction(self::createStub(NewsletterChannel::class));

        self::assertSame(
            ['error.sendNotConfirmed'],
            $subject->forwardedFlashMessages,
            'A non-POST send request must be answered with the "not confirmed" message.',
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
        /** @var TestableUniversalMessengerController $subject */
        $subject = (new ReflectionClass(TestableUniversalMessengerController::class))
            ->newInstanceWithoutConstructor();

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
     * Both properties are resolved against the controller itself: "request" is
     * inherited and protected, "eventFileRepository" is private and declared here.
     */
    private function injectProperty(object $subject, string $name, object $value): void
    {
        $property = new ReflectionProperty(UniversalMessengerController::class, $name);

        $property->setValue($subject, $value);
    }
}
