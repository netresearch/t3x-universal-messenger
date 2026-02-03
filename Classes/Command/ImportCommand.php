<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Command;

use DateTime;
use Exception;
use Netresearch\Sdk\UniversalMessenger\Model\Collection\NewsletterChannelCollection;
use Netresearch\Sdk\UniversalMessenger\Model\NewsletterChannel;
use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Domain\Model\NewsletterChannel as NewsletterChannelDomainModel;
use Netresearch\UniversalMessenger\Domain\Repository\NewsletterChannelRepository;
use Netresearch\UniversalMessenger\Repository\NewsletterRepository;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

use function sprintf;

/**
 * Class ImportCommand.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class ImportCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var SymfonyStyle
     */
    private SymfonyStyle $symfonyStyle;

    /**
     * @var PersistenceManagerInterface
     */
    private PersistenceManagerInterface $persistenceManager;

    /**
     * @var NewsletterChannelRepository
     */
    private NewsletterChannelRepository $newsletterChannelRepository;

    /**
     * @var NewsletterRepository
     */
    private NewsletterRepository $newsletterRepository;

    /**
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * Configures the command.
     *
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription('Imports all active Universal Messenger newsletter channels');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->symfonyStyle = new SymfonyStyle($input, $output);
        $this->symfonyStyle->title($this->getName() ?? '');

        $this->initCliEnvironment();
        $this->bootstrap();

        return $this->importNewsletterChannels();
    }

    /**
     * Create a request object as with TYPO3 v13.3 extbase components rely on a request instance.
     *
     * @return void
     *
     * @see https://forge.typo3.org/issues/105554
     * @see https://forge.typo3.org/issues/105616
     * @see https://forge.typo3.org/issues/105954
     */
    private function initCliEnvironment(): void
    {
        if ((PHP_SAPI === 'cli') && !isset($GLOBALS['TYPO3_REQUEST'])) {
            $serverRequest = (new ServerRequest())
                ->withAttribute('extbase', [])
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

            $GLOBALS['TYPO3_REQUEST'] = $serverRequest;
        }
    }

    /**
     * Bootstrap.
     */
    private function bootstrap(): void
    {
        $this->persistenceManager          = GeneralUtility::makeInstance(PersistenceManagerInterface::class);
        $this->newsletterChannelRepository = GeneralUtility::makeInstance(NewsletterChannelRepository::class);
        $this->newsletterRepository        = GeneralUtility::makeInstance(NewsletterRepository::class);
        $this->configuration               = GeneralUtility::makeInstance(Configuration::class);
    }

    /**
     * @return int
     */
    private function importNewsletterChannels(): int
    {
        // Query all active newsletter channels from UM webservice
        $newsletterChannelCollection = $this->queryNewsletterChannelCollection();
        $storagePid                  = $this->getStoragePageId();

        // Abort further import if webservice response does not contain any data
        if ($newsletterChannelCollection->count() === 0) {
            // Return an error code
            return self::FAILURE;
        }

        $this->symfonyStyle->text('Perform import');
        $this->symfonyStyle->newLine();
        $this->symfonyStyle->progressStart($newsletterChannelCollection->count());

        // List of newsletter channels imported
        $channelIds = [];

        foreach ($newsletterChannelCollection as $newsletterChannel) {
            $channelIds[] = $this->stripChannelSuffix($newsletterChannel->id);

            try {
                // Newsletter channel
                $newsletterChannelDomainModel = $this->hydrateNewsletterChannel(
                    $newsletterChannel,
                    $storagePid
                );

                // Add the new entity and persist it
                $this->newsletterChannelRepository->add($newsletterChannelDomainModel);
                $this->persistenceManager->persistAll();
            } catch (Exception $exception) {
                $this->handleException($exception);
            }

            $this->symfonyStyle->progressAdvance();
        }

        $this->symfonyStyle->progressFinish();

        // Remove all obsolete records
        $this->removeObsoleteRecords($channelIds);

        $this->symfonyStyle->success('Import done');

        // All fine
        return self::SUCCESS;
    }

    /**
     * Query all active newsletter channels from UM webservice.
     *
     * @return NewsletterChannelCollection
     */
    private function queryNewsletterChannelCollection(): NewsletterChannelCollection
    {
        try {
            $this->symfonyStyle->note('Download updated list of newsletter channels from Universal Messenger');

            return $this->newsletterRepository->findAllChannels();
        } catch (Exception $exception) {
            $this->handleException($exception);
        }

        return new NewsletterChannelCollection();
    }

    /**
     * Hydrate a newsletter channel record with the given Universal Messenger webservice response.
     *
     * @param NewsletterChannel $newsletterChannel
     * @param int<0, max>       $storagePid
     *
     * @return NewsletterChannelDomainModel
     */
    private function hydrateNewsletterChannel(
        NewsletterChannel $newsletterChannel,
        int $storagePid,
    ): NewsletterChannelDomainModel {
        $channelId = $this->stripChannelSuffix($newsletterChannel->id);

        $newsletterChannelDomainModel = $this->newsletterChannelRepository
            ->findByChannelId($channelId);

        // Create new record if it not exists in the database
        if (!$newsletterChannelDomainModel instanceof NewsletterChannelDomainModel) {
            /** @var NewsletterChannelDomainModel $newsletterChannelDomainModel */
            $newsletterChannelDomainModel = GeneralUtility::makeInstance(NewsletterChannelDomainModel::class);
            $newsletterChannelDomainModel->setPid($storagePid);
            $newsletterChannelDomainModel->setChannelId($channelId);
        }

        $title = $this->getUpdatedValue(
            $newsletterChannelDomainModel->getTitle(),
            $this->cleanChannelTitle($newsletterChannel->title)
        );

        $description = $this->getUpdatedValue(
            $newsletterChannelDomainModel->getDescription(),
            $this->cleanChannelTitle($newsletterChannel->description)
        );

        $newsletterChannelDomainModel
            ->setTitle($title)
            ->setDescription($description)
            ->setCrdate(new DateTime())
            ->setTstamp(new DateTime());

        return $newsletterChannelDomainModel;
    }

    /**
     * Returns either the current value or the updated value if the updated is not empty.
     *
     * @param string $existingValue
     * @param string $updatedValue
     *
     * @return string
     */
    private function getUpdatedValue(string $existingValue, string $updatedValue): string
    {
        if (
            ($existingValue === '')
            || (
                ($updatedValue !== '')
                && ($existingValue !== $updatedValue)
            )
        ) {
            return $updatedValue;
        }

        return $existingValue;
    }

    /**
     * Removes the configured channel suffixes from the given channel ID.
     *
     * @param string $channelId
     *
     * @return string
     */
    private function stripChannelSuffix(string $channelId): string
    {
        return trim(
            str_ireplace(
                [
                    $this->getTestChannelSuffix(),
                    $this->getLiveChannelSuffix(),
                ],
                '',
                $channelId
            )
        );
    }

    /**
     * Removes some configured text parts from the newsletter channel title.
     *
     * @param string $channelTitle
     *
     * @return string
     */
    private function cleanChannelTitle(string $channelTitle): string
    {
        return trim(
            str_ireplace(
                [
                    '(TESTVersand)',
                    '(LIVEVersand)',
                ],
                '',
                $channelTitle
            )
        );
    }

    /**
     * Finds all records not in the list of imported records and remove them.
     *
     * @param string[] $channelIds
     *
     * @return void
     */
    private function removeObsoleteRecords(array $channelIds): void
    {
        try {
            $this->symfonyStyle->text('Remove obsolete records');
            $this->symfonyStyle->newLine();

            $queryResult = $this->newsletterChannelRepository
                ->findAllExceptWithChannelId($channelIds);

            // Remove each record
            foreach ($queryResult as $newsletterChannel) {
                $this->newsletterChannelRepository->remove($newsletterChannel);
            }

            // Persist everything
            $this->persistenceManager->persistAll();
        } catch (Exception $exception) {
            $this->handleException($exception);
        }
    }

    /**
     * Returns the page ID used to store the records.
     *
     * @return int<0, max>
     */
    private function getStoragePageId(): int
    {
        return (int) ($this->configuration->getExtensionSetting('storagePageId') ?? 0);
    }

    /**
     * Returns the TEST newsletter channel suffix.
     *
     * @return string
     */
    private function getTestChannelSuffix(): string
    {
        return $this->configuration->getExtensionSetting('newsletter/testChannelSuffix') ?? '';
    }

    /**
     * Returns the LIVE newsletter channel suffix.
     *
     * @return string
     */
    private function getLiveChannelSuffix(): string
    {
        return $this->configuration->getExtensionSetting('newsletter/liveChannelSuffix') ?? '';
    }

    /**
     * Handles the exception processing.
     *
     * @param Throwable $exception
     *
     * @return void
     */
    private function handleException(Throwable $exception): void
    {
        $this->symfonyStyle->writeln(
            sprintf(
                '<error>%s</error>',
                $exception->getMessage()
            )
        );

        $this->logger?->error(
            $exception->getMessage(),
            [
                'exception' => $exception,
            ]
        );
    }
}
