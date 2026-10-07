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

namespace Netresearch\UniversalMessenger\Tests\Functional\Domain\Repository;

use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Domain\Model\NewsletterChannel;
use Netresearch\UniversalMessenger\Domain\Repository\NewsletterChannelRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Tests the newsletter channel repository against a real database: it only
 * sees the channels stored on the configured storage page, maps their
 * columns onto the model, and finds the channels no longer delivered by
 * the Universal Messenger.
 *
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(NewsletterChannelRepository::class)]
#[CoversClass(NewsletterChannel::class)]
#[CoversClass(Configuration::class)]
final class NewsletterChannelRepositoryTest extends FunctionalTestCase
{
    /**
     * @var int
     */
    private const STORAGE_PAGE_ID = 42;

    /**
     * @var string
     */
    private const TABLE = 'tx_universalmessenger_domain_model_newsletterchannel';

    protected array $testExtensionsToLoad = [
        'netresearch/universal-messenger',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'universal_messenger' => [
                'storagePageId' => '42',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $connection = $this->getConnectionPool()->getConnectionForTable(self::TABLE);

        $connection->insert(self::TABLE, [
            'pid'          => self::STORAGE_PAGE_ID,
            'channel_id'   => 'crmDemoChannel',
            'title'        => 'Camino CRM Demo Channel',
            'description'  => 'Monthly product news',
            'sender'       => 'newsletter@example.org',
            'reply_to'     => 'support@example.org',
            'skip_used_id' => 1,
            'embed_images' => 'all',
        ]);
        $connection->insert(self::TABLE, [
            'pid'        => self::STORAGE_PAGE_ID,
            'channel_id' => 'pressChannel',
            'title'      => 'Press releases',
        ]);
        $connection->insert(self::TABLE, [
            'pid'        => self::STORAGE_PAGE_ID,
            'channel_id' => 'eventsChannel',
            'title'      => 'Events',
        ]);
        $connection->insert(self::TABLE, [
            'pid'        => self::STORAGE_PAGE_ID + 1,
            'channel_id' => 'foreignChannel',
            'title'      => 'Stored on another page',
        ]);
    }

    #[Test]
    public function findsAChannelByItsIdAndMapsEveryColumn(): void
    {
        $channel = $this->createSubject()->findByChannelId('crmDemoChannel');

        self::assertInstanceOf(NewsletterChannel::class, $channel);
        self::assertSame(self::STORAGE_PAGE_ID, $channel->getPid());
        self::assertSame('crmDemoChannel', $channel->getChannelId());
        self::assertSame('Camino CRM Demo Channel', $channel->getTitle());
        self::assertSame('Monthly product news', $channel->getDescription());
        self::assertSame('newsletter@example.org', $channel->getSender());
        self::assertSame('support@example.org', $channel->getReplyTo());
        self::assertTrue($channel->isSkipUsedId());
        self::assertSame('all', $channel->getEmbedImages());
    }

    #[Test]
    public function findsNoChannelForAnUnknownId(): void
    {
        self::assertNull($this->createSubject()->findByChannelId('unknownChannel'));
    }

    #[Test]
    public function ignoresChannelsStoredOutsideTheConfiguredStoragePage(): void
    {
        self::assertNull($this->createSubject()->findByChannelId('foreignChannel'));
    }

    #[Test]
    public function findsTheStoredChannelsMissingFromTheGivenList(): void
    {
        $channelIds = array_map(
            static fn (NewsletterChannel $channel): string => $channel->getChannelId(),
            $this->createSubject()
                ->findAllExceptWithChannelId(['crmDemoChannel', 'eventsChannel', 'notStoredChannel'])
                ->toArray(),
        );

        self::assertSame(['pressChannel'], $channelIds);
    }

    private function createSubject(): NewsletterChannelRepository
    {
        return GeneralUtility::makeInstance(NewsletterChannelRepository::class);
    }
}
