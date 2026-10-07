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

namespace Netresearch\UniversalMessenger\Tests\Functional\Command;

use Netresearch\Sdk\UniversalMessenger\Exception\ServiceException;
use Netresearch\Sdk\UniversalMessenger\Model\Collection\NewsletterChannelCollection;
use Netresearch\Sdk\UniversalMessenger\Model\NewsletterChannel as SdkNewsletterChannel;
use Netresearch\UniversalMessenger\Command\ImportCommand;
use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Domain\Model\NewsletterChannel;
use Netresearch\UniversalMessenger\Domain\Repository\NewsletterChannelRepository;
use Netresearch\UniversalMessenger\Repository\NewsletterRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Runs the newsletter channel import against a real database, with its real
 * collaborators resolved by the command itself. Only the Universal Messenger
 * webservice is replaced: the repository asking it for the channel list.
 *
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(ImportCommand::class)]
#[CoversClass(NewsletterChannelRepository::class)]
#[CoversClass(NewsletterChannel::class)]
#[CoversClass(Configuration::class)]
final class ImportCommandTest extends FunctionalTestCase
{
    /**
     * @var string
     */
    private const WEBSERVICE_FAILURE = 'The webservice is unavailable.';

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
                'newsletter'    => [
                    'testChannelSuffix' => '_Test',
                    'liveChannelSuffix' => '_Live',
                ],
            ],
        ],
    ];

    private ?NewsletterRepository $newsletterRepository = null;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = $this->getConnectionPool()->getConnectionForTable(self::TABLE);

        $connection->insert(self::TABLE, [
            'pid'         => self::STORAGE_PAGE_ID,
            'channel_id'  => 'crmDemoChannel',
            'title'       => 'Outdated title',
            'description' => 'Kept description',
            'sender'      => 'newsletter@example.org',
            'crdate'      => 1577836800,
        ]);
        $connection->insert(self::TABLE, [
            'pid'        => self::STORAGE_PAGE_ID,
            'channel_id' => 'obsoleteChannel',
            'title'      => 'No longer delivered by the webservice',
        ]);
        $connection->insert(self::TABLE, [
            'pid'        => self::STORAGE_PAGE_ID + 1,
            'channel_id' => 'foreignChannel',
            'title'      => 'Stored outside the storage page',
        ]);
    }

    protected function tearDown(): void
    {
        // The command creates a CLI request when none exists, and the
        // webservice repository was replaced; leave neither behind for the
        // next test case.
        unset($GLOBALS['TYPO3_REQUEST']);

        if ($this->newsletterRepository instanceof NewsletterRepository) {
            GeneralUtility::removeSingletonInstance(NewsletterRepository::class, $this->newsletterRepository);
        }

        parent::tearDown();
    }

    /**
     * The test and live variants of one channel are merged into one record,
     * a new channel is created on the storage page, a channel no longer
     * delivered is deleted and a record outside the storage page is kept.
     */
    #[Test]
    public function synchronisesTheStoredChannelsWithTheWebserviceResponse(): void
    {
        $this->stubWebserviceChannels(new NewsletterChannelCollection([
            $this->sdkChannel('crmDemoChannel_Test', 'CRM Demo (TESTVersand)'),
            $this->sdkChannel('crmDemoChannel_Live', 'CRM Demo (LIVEVersand)'),
            $this->sdkChannel('newChannel_Live', 'New channel', 'Fresh description'),
        ]));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('(crmDemoChannel_Test, crmDemoChannel_Live) map to the same channel ID "crmDemoChannel"'));
        $logger
            ->expects(self::never())
            ->method('error');

        $commandTester = $this->runImport($logger);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('Import done', $commandTester->getDisplay());

        $rows = $this->fetchChannelRows();

        self::assertSame(
            ['crmDemoChannel', 'foreignChannel', 'newChannel'],
            array_keys($rows),
        );

        self::assertSame(self::STORAGE_PAGE_ID, (int) $rows['crmDemoChannel']['pid']);
        self::assertSame('CRM Demo', $rows['crmDemoChannel']['title']);
        self::assertSame(
            'Kept description',
            $rows['crmDemoChannel']['description'],
            'An empty description from the webservice must not wipe the stored one.',
        );
        self::assertSame('newsletter@example.org', $rows['crmDemoChannel']['sender']);
        self::assertSame(1577836800, (int) $rows['crmDemoChannel']['crdate']);
        self::assertGreaterThan(1577836800, (int) $rows['crmDemoChannel']['tstamp']);

        self::assertSame(self::STORAGE_PAGE_ID, (int) $rows['newChannel']['pid']);
        self::assertSame('New channel', $rows['newChannel']['title']);
        self::assertSame('Fresh description', $rows['newChannel']['description']);
        self::assertGreaterThan(0, (int) $rows['newChannel']['crdate']);

        self::assertSame(self::STORAGE_PAGE_ID + 1, (int) $rows['foreignChannel']['pid']);
    }

    /**
     * An empty response must not be mistaken for "every channel was
     * removed": the import aborts before touching a single record.
     */
    #[Test]
    public function abortsWithoutChangesWhenTheWebserviceReturnsNoChannels(): void
    {
        $this->stubWebserviceChannels(new NewsletterChannelCollection());

        $commandTester = $this->runImport();

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertSame(
            ['crmDemoChannel', 'foreignChannel', 'obsoleteChannel'],
            array_keys($this->fetchChannelRows()),
        );
    }

    #[Test]
    public function reportsAWebserviceFailureAndKeepsTheStoredChannels(): void
    {
        $serviceException = new ServiceException(self::WEBSERVICE_FAILURE);

        $newsletterRepository = self::createStub(NewsletterRepository::class);
        $newsletterRepository
            ->method('findAllChannels')
            ->willThrowException($serviceException);

        $this->replaceNewsletterRepository($newsletterRepository);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::WEBSERVICE_FAILURE,
                ['exception' => $serviceException],
            );

        $commandTester = $this->runImport($logger);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString(self::WEBSERVICE_FAILURE, $commandTester->getDisplay());
        self::assertSame(
            ['crmDemoChannel', 'foreignChannel', 'obsoleteChannel'],
            array_keys($this->fetchChannelRows()),
        );
    }

    private function stubWebserviceChannels(NewsletterChannelCollection $newsletterChannelCollection): void
    {
        $newsletterRepository = self::createStub(NewsletterRepository::class);
        $newsletterRepository
            ->method('findAllChannels')
            ->willReturn($newsletterChannelCollection);

        $this->replaceNewsletterRepository($newsletterRepository);
    }

    /**
     * The command resolves the repository via GeneralUtility::makeInstance(),
     * which returns a registered singleton instance before asking the
     * container.
     */
    private function replaceNewsletterRepository(NewsletterRepository $newsletterRepository): void
    {
        $this->newsletterRepository = $newsletterRepository;

        GeneralUtility::setSingletonInstance(NewsletterRepository::class, $newsletterRepository);
    }

    private function runImport(?LoggerInterface $logger = null): CommandTester
    {
        $command = new ImportCommand('universal-messenger:newsletter-channels:import');

        if ($logger instanceof LoggerInterface) {
            $command->setLogger($logger);
        }

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        return $commandTester;
    }

    private function sdkChannel(string $id, string $title, string $description = ''): SdkNewsletterChannel
    {
        $channel              = new SdkNewsletterChannel();
        $channel->id          = $id;
        $channel->title       = $title;
        $channel->description = $description;

        return $channel;
    }

    /**
     * @return array<string, array<string, mixed>> The stored rows, indexed and sorted by channel ID
     */
    private function fetchChannelRows(): array
    {
        $rows = $this->getConnectionPool()
            ->getConnectionForTable(self::TABLE)
            ->select(['*'], self::TABLE)
            ->fetchAllAssociative();

        $rowsByChannelId = array_column($rows, null, 'channel_id');
        ksort($rowsByChannelId);

        return $rowsByChannelId;
    }
}
