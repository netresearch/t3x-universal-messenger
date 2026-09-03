<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit\Service;

use Netresearch\Sdk\UniversalMessenger\Model\NewsletterChannel;
use Netresearch\UniversalMessenger\Service\ChannelDeduplicationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests that channels sharing the same channel ID after stripping the configured
 * test/live suffix are grouped together and resolved deterministically, instead of
 * silently overwriting each other's title/description depending on API response order.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(ChannelDeduplicationService::class)]
final class ChannelDeduplicationServiceTest extends UnitTestCase
{
    private function createChannel(string $id, string $title): NewsletterChannel
    {
        $channel        = new NewsletterChannel();
        $channel->id    = $id;
        $channel->title = $title;

        return $channel;
    }

    #[Test]
    public function groupByStrippedIdCombinesAGenuineTestLivePairUnderOneKey(): void
    {
        $subject = new ChannelDeduplicationService();

        $channels = [
            $this->createChannel('demo_angebote', 'Angebote'),
            $this->createChannel('newsletter_Test', 'newsletter_Test'),
            $this->createChannel('newsletter_Live', 'newsletter_Live'),
        ];

        $grouped = $subject->groupByStrippedId($channels, ['_Test', '_Live']);

        self::assertSame(['demo_angebote', 'newsletter'], array_keys($grouped));
        self::assertCount(2, $grouped['newsletter']);
    }

    #[Test]
    public function selectCanonicalChannelPrefersTheChannelWhoseRawIdMatchesTheStrippedIdExactly(): void
    {
        $subject = new ChannelDeduplicationService();

        // Reproduces the reported collision: a genuine, independent channel
        // "crmDemoChannel" coexists with two unrelated auto-created test/live
        // channels sharing the same base name.
        $baseChannel = $this->createChannel('crmDemoChannel', 'CRM Demo Liste');
        $candidates  = [
            $this->createChannel('crmDemoChannel_Live', 'crmDemoChannel_Live'),
            $baseChannel,
            $this->createChannel('crmDemoChannel_Test', 'crmDemoChannel_Test'),
        ];

        $canonical = $subject->selectCanonicalChannel('crmDemoChannel', $candidates);

        self::assertSame($baseChannel, $canonical);
        self::assertSame('CRM Demo Liste', $canonical->title);
    }

    #[Test]
    public function selectCanonicalChannelFallsBackToTheAlphabeticallyFirstIdWhenNoExactMatchExists(): void
    {
        $subject = new ChannelDeduplicationService();

        $liveChannel = $this->createChannel('newsletter_Live', 'newsletter_Live');
        $testChannel = $this->createChannel('newsletter_Test', 'newsletter_Test');

        // Deliberately passed in an order where the "wrong" one would win if the
        // selection depended on iteration order instead of being deterministic.
        $canonical = $subject->selectCanonicalChannel('newsletter', [$testChannel, $liveChannel]);

        self::assertSame($liveChannel, $canonical);
    }

    #[Test]
    public function groupByStrippedIdOnlyStripsASuffixMatchingTheEndOfTheChannelId(): void
    {
        $subject = new ChannelDeduplicationService();

        // "newsletter_Test_archive" contains "_Test" in the middle, not at the end,
        // stripping it there would incorrectly collide it with "newsletter_archive".
        $channels = [
            $this->createChannel('newsletter_archive', 'Archiv'),
            $this->createChannel('newsletter_Test_archive', 'Archiv (Test)'),
        ];

        $grouped = $subject->groupByStrippedId($channels, ['_Test', '_Live']);

        self::assertSame(['newsletter_archive', 'newsletter_Test_archive'], array_keys($grouped));
    }

    #[Test]
    public function isAmbiguousGroupIsFalseForASingleChannel(): void
    {
        $subject = new ChannelDeduplicationService();

        self::assertFalse(
            $subject->isAmbiguousGroup([$this->createChannel('demo_angebote', 'Angebote')]),
        );
    }

    #[Test]
    public function isAmbiguousGroupIsTrueForMultipleChannels(): void
    {
        $subject = new ChannelDeduplicationService();

        $candidates = [
            $this->createChannel('crmDemoChannel', 'CRM Demo Liste'),
            $this->createChannel('crmDemoChannel_Test', 'crmDemoChannel_Test'),
        ];

        self::assertTrue($subject->isAmbiguousGroup($candidates));
    }
}
