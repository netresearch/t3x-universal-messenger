<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit\Command;

use DateTime;
use Netresearch\Sdk\UniversalMessenger\Model\Collection\NewsletterChannelCollection;
use Netresearch\Sdk\UniversalMessenger\Model\NewsletterChannel as SdkNewsletterChannel;
use Netresearch\UniversalMessenger\Command\ImportCommand;
use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Domain\Model\NewsletterChannel as NewsletterChannelDomainModel;
use Netresearch\UniversalMessenger\Domain\Repository\NewsletterChannelRepository;
use Netresearch\UniversalMessenger\Repository\NewsletterRepository;
use Netresearch\UniversalMessenger\Service\ChannelDeduplicationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Importing an already-known newsletter channel must not reset its creation
 * date on every scheduled/CLI run; a genuinely new channel must still get
 * one assigned.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(ImportCommand::class)]
final class ImportCommandTest extends UnitTestCase
{
    /**
     * Reimporting a channel that already exists in the database must keep
     * its original creation date. Only title/description/tstamp may change.
     */
    #[Test]
    public function preservesTheCreationDateOfAnAlreadyImportedChannel(): void
    {
        $originalCrdate = new DateTime('2020-01-01T00:00:00+00:00');

        $existingDomainModel = (new NewsletterChannelDomainModel())
            ->setChannelId('newsletter_test')
            ->setTitle('Old title')
            ->setDescription('Old description')
            ->setCrdate($originalCrdate)
            ->setTstamp($originalCrdate);

        $addedDomainModels = $this->runImport($existingDomainModel);

        self::assertCount(
            1,
            $addedDomainModels,
            'Exactly one grouped channel from the webservice response must be imported.',
        );
        self::assertSame(
            $originalCrdate,
            $addedDomainModels[0]->getCrdate(),
            'The creation date of an already existing channel must not be reset on every import run.',
        );
        self::assertSame(
            'New title',
            $addedDomainModels[0]->getTitle(),
            'The title must still be refreshed from the webservice response.',
        );
    }

    /**
     * A channel that does not exist yet in the database must still get a
     * creation date assigned, so this must keep working alongside the fix
     * that stops it from being reset on every subsequent import.
     */
    #[Test]
    public function assignsACreationDateToAGenuinelyNewChannel(): void
    {
        $addedDomainModels = $this->runImport(null);

        self::assertCount(
            1,
            $addedDomainModels,
            'Exactly one grouped channel from the webservice response must be imported.',
        );
        self::assertInstanceOf(
            DateTime::class,
            $addedDomainModels[0]->getCrdate(),
            'A genuinely new channel must be assigned a creation date.',
        );
    }

    /**
     * Runs the import command against a single Universal Messenger channel
     * response, with findByChannelId() returning the given existing domain
     * model, or null to exercise the "genuinely new channel" branch.
     *
     * @param NewsletterChannelDomainModel|null $existingDomainModel
     *
     * @return NewsletterChannelDomainModel[]
     */
    private function runImport(?NewsletterChannelDomainModel $existingDomainModel): array
    {
        $rawChannel              = new SdkNewsletterChannel();
        $rawChannel->id          = 'newsletter_test';
        $rawChannel->title       = 'New title';
        $rawChannel->description = 'New description';

        $newsletterChannelRepository = $this->createMock(NewsletterChannelRepository::class);
        $newsletterChannelRepository
            ->expects($this->once())
            ->method('findByChannelId')
            ->with('newsletter_test')
            ->willReturn($existingDomainModel);
        $newsletterChannelRepository
            ->method('findAllExceptWithChannelId')
            ->willReturn(self::createStub(QueryResultInterface::class));

        $addedDomainModels = [];
        $newsletterChannelRepository
            ->method('add')
            ->willReturnCallback(
                static function (NewsletterChannelDomainModel $domainModel) use (&$addedDomainModels): void {
                    $addedDomainModels[] = $domainModel;
                },
            );

        $newsletterRepository = self::createStub(NewsletterRepository::class);
        $newsletterRepository
            ->method('findAllChannels')
            ->willReturn(new NewsletterChannelCollection([$rawChannel]));

        $configuration = self::createStub(Configuration::class);
        $configuration
            ->method('getExtensionSetting')
            ->willReturnMap(
                [
                    ['storagePageId', '1'],
                    ['newsletter/testChannelSuffix', ''],
                    ['newsletter/liveChannelSuffix', ''],
                ],
            );

        $persistenceManager = self::createStub(PersistenceManagerInterface::class);

        $subject = new TestableImportCommand();

        $collaborators = [
            'persistenceManager'          => $persistenceManager,
            'newsletterChannelRepository' => $newsletterChannelRepository,
            'newsletterRepository'        => $newsletterRepository,
            'configuration'               => $configuration,
            'channelDeduplicationService' => new ChannelDeduplicationService(),
        ];

        foreach ($collaborators as $propertyName => $value) {
            $property = new ReflectionProperty(ImportCommand::class, $propertyName);
            $property->setValue($subject, $value);
        }

        $exitCode = $subject->run(
            new ArrayInput([]),
            new BufferedOutput(),
        );

        self::assertSame(
            ImportCommand::SUCCESS,
            $exitCode,
            'The import must succeed.',
        );

        return $addedDomainModels;
    }
}
