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

namespace Netresearch\UniversalMessenger\Tests\Unit\Domain\Model;

use DateTime;
use Netresearch\UniversalMessenger\Domain\Model\NewsletterChannel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests the defaults of a newly created newsletter channel and that every
 * setter stores its value and returns the same channel for chaining, which
 * the import command relies on when it hydrates a channel.
 *
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(NewsletterChannel::class)]
final class NewsletterChannelTest extends UnitTestCase
{
    #[Test]
    public function aNewChannelStartsEmptyWithImageEmbeddingDisabled(): void
    {
        $subject = new NewsletterChannel();

        self::assertNull($subject->getCrdate());
        self::assertNull($subject->getTstamp());
        self::assertSame('', $subject->getChannelId());
        self::assertSame('', $subject->getTitle());
        self::assertSame('', $subject->getDescription());
        self::assertSame('', $subject->getSender());
        self::assertSame('', $subject->getReplyTo());
        self::assertFalse($subject->isSkipUsedId());
        self::assertSame('none', $subject->getEmbedImages());
    }

    #[Test]
    public function everySetterStoresItsValueAndReturnsTheSameChannel(): void
    {
        $crdate  = new DateTime('2024-05-14T10:00:00+00:00');
        $tstamp  = new DateTime('2024-05-15T11:30:00+00:00');
        $subject = new NewsletterChannel();

        $result = $subject
            ->setCrdate($crdate)
            ->setTstamp($tstamp)
            ->setChannelId('crmDemoChannel')
            ->setTitle('Camino CRM Demo Channel')
            ->setDescription('Monthly product news')
            ->setSender('newsletter@example.org')
            ->setReplyTo('support@example.org')
            ->setSkipUsedId(true)
            ->setEmbedImages('all');

        self::assertSame($subject, $result);
        self::assertSame($crdate, $subject->getCrdate());
        self::assertSame($tstamp, $subject->getTstamp());
        self::assertSame('crmDemoChannel', $subject->getChannelId());
        self::assertSame('Camino CRM Demo Channel', $subject->getTitle());
        self::assertSame('Monthly product news', $subject->getDescription());
        self::assertSame('newsletter@example.org', $subject->getSender());
        self::assertSame('support@example.org', $subject->getReplyTo());
        self::assertTrue($subject->isSkipUsedId());
        self::assertSame('all', $subject->getEmbedImages());
    }

    #[Test]
    public function theDatesCanBeClearedAgain(): void
    {
        $subject = (new NewsletterChannel())
            ->setCrdate(new DateTime())
            ->setTstamp(new DateTime());

        $subject
            ->setCrdate(null)
            ->setTstamp(null);

        self::assertNull($subject->getCrdate());
        self::assertNull($subject->getTstamp());
    }
}
