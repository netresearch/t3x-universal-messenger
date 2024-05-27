<?php

/**
 * This file is part of the package netresearch/nrc-universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrcUniversalMessenger\Command;

use DateTime;
use Exception;
use Netresearch\NrcUniversalMessenger\Domain\Model\NewsletterChannel as NewsletterChannelDomainModel;
use Netresearch\NrcUniversalMessenger\Domain\Repository\NewsletterChannelRepository;
use Netresearch\NrcUniversalMessenger\Service\UniversalMessengerService;
use Netresearch\Sdk\UniversalMessenger\Model\Collection\NewsletterChannelCollection;
use Netresearch\Sdk\UniversalMessenger\Model\NewsletterChannel;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

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
     * @var PersistenceManagerInterface
     */
    private PersistenceManagerInterface $persistenceManager;

    /**
     * @var UniversalMessengerService
     */
    private UniversalMessengerService $universalMessengerService;

    /**
     * @var NewsletterChannelRepository
     */
    private NewsletterChannelRepository $newsletterChannelRepository;

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
     * Bootstrap.
     */
    protected function bootstrap(): void
    {
        $this->persistenceManager          = GeneralUtility::makeInstance(PersistenceManagerInterface::class);
        $this->universalMessengerService   = GeneralUtility::makeInstance(UniversalMessengerService::class);
        $this->newsletterChannelRepository = GeneralUtility::makeInstance(NewsletterChannelRepository::class);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrap();

        return $this->importNewsletterChannels($output);
    }

    /**
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function importNewsletterChannels(OutputInterface $output): int
    {
        // Query all active newsletter channels from UM webservice
        $newsletterChannelCollection = $this->queryNewsletterChannelCollection($output);

        // Abort further import if webservice response does not contain any data
        if ($newsletterChannelCollection->count() === 0) {
            // Return an error code
            return self::FAILURE;
        }

        $storagePid = $this->getStoragePageId();

        $output->writeln('Start importing...');
        $count = 0;

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

                // Add the new entity to the repository
                $this->newsletterChannelRepository->add($newsletterChannelDomainModel);

                // Persist the new entity
                $this->persistenceManager->persistAll();

                ++$count;

                $output->write('.');

                if (($count % 10) === 0) {
                    $output->writeln(
                        sprintf(' %6d', $count)
                    );
                }
            } catch (Exception $exception) {
                $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

                $this->logger->error(
                    $exception->getMessage(),
                    [
                        'exception' => $exception,
                    ]
                );
            }
        }

        // Remove all obsolete records
        $this->removeObsoleteRecords($channelIds, $output);

        $output->writeln("\nImport done");

        // All fine
        return self::SUCCESS;
    }

    /**
     * Query all active newsletter channels from UM webservice.
     *
     * @param OutputInterface $output
     *
     * @return NewsletterChannelCollection
     */
    private function queryNewsletterChannelCollection(OutputInterface $output): NewsletterChannelCollection
    {
        try {
            $output->writeln('Download updated list of newsletter channels from Universal Messenger');

            return $this->universalMessengerService
                ->api()
                ->newsletter()
                ->channels();
        } catch (Exception $exception) {
            $this->logger->error(
                $exception->getMessage(),
                [
                    'exception' => $exception,
                ]
            );

            $output->writeln($exception->getMessage());
        }

        return new NewsletterChannelCollection();
    }

    /**
     * Hydrate a newsletter channel record with the given Universal Messenger webservice response.
     *
     * @param NewsletterChannel $newsletterChannel
     * @param int               $storagePid
     *
     * @return NewsletterChannelDomainModel
     */
    private function hydrateNewsletterChannel(
        NewsletterChannel $newsletterChannel,
        int $storagePid
    ): NewsletterChannelDomainModel {
        $channelId = $this->stripChannelSuffix($newsletterChannel->id);

        $newsletterChannelDomainModel = $this->newsletterChannelRepository
            ->findByChannelId($channelId);

        if ($newsletterChannelDomainModel instanceof NewsletterChannelDomainModel) {
            return $newsletterChannelDomainModel;
        }

        $newsletterChannelDomainModel = GeneralUtility::makeInstance(NewsletterChannelDomainModel::class);
        $newsletterChannelDomainModel->setPid($storagePid);

        $newsletterChannelDomainModel
            ->setChannelId($channelId)
            ->setTitle($this->cleanChannelTitle($newsletterChannel->title))
            ->setDescription($newsletterChannel->description)
            ->setCrdate(new DateTime())
            ->setTstamp(new DateTime());

        return $newsletterChannelDomainModel;
    }

    /**
     * Removes the channel suffix "_Test" and "_Live" from the given channel ID.
     *
     * @param string $channelId
     *
     * @return string
     */
    private function stripChannelSuffix(string $channelId): string
    {
        return trim(str_ireplace(['_Test', '_Live'], '', $channelId));
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
        return trim(str_ireplace('(TESTVersand)', '', $channelTitle));
    }

    /**
     * Finds all records not in the list of imported records and remove them.
     *
     * @param string[]        $channelIds
     * @param OutputInterface $output
     *
     * @return void
     */
    private function removeObsoleteRecords(array $channelIds, OutputInterface $output): void
    {
        try {
            $queryResult = $this->newsletterChannelRepository
                ->findAllNotByChannelId($channelIds);

            // Remove each record
            foreach ($queryResult as $newsletterChannel) {
                $this->newsletterChannelRepository->remove($newsletterChannel);
            }

            // Persist everything
            $this->persistenceManager->persistAll();
        } catch (Exception $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            $this->logger->error(
                $exception->getMessage(),
                [
                    'exception' => $exception,
                ]
            );
        }
    }

    /**
     * Get the extension configuration.
     *
     * @param string $path Path to get the config for
     *
     * @return mixed
     */
    protected function getExtensionConfiguration(string $path): mixed
    {
        try {
            return GeneralUtility::makeInstance(ExtensionConfiguration::class)
                ->get('nrc_universal_messenger', $path);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Returns the page ID used to store the records.
     *
     * @return int
     */
    protected function getStoragePageId(): int
    {
        return (int) ($this->getExtensionConfiguration('universalMessengerStoragePid') ?? 0);
    }
}
