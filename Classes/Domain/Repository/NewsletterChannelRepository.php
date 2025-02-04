<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Domain\Repository;

use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Domain\Model\NewsletterChannel;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * The newsletter channel repository.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 *
 * @extends Repository<NewsletterChannel>
 */
class NewsletterChannelRepository extends Repository
{
    /**
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * NewsletterChannelRepository constructor.
     *
     * @param Configuration $configuration
     */
    public function __construct(
        Configuration $configuration,
    ) {
        parent::__construct();

        $this->configuration = $configuration;
    }

    /**
     * Initializes the repository.
     *
     * @return void
     */
    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings
            ->setRespectStoragePage(true)
            ->setStoragePageIds([$this->getStoragePageId()]);

        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Returns the page ID used to store the records.
     *
     * @return int
     */
    private function getStoragePageId(): int
    {
        return (int) ($this->configuration->getExtensionSetting('storagePageId') ?? 0);
    }

    /**
     * @param string $channelId
     *
     * @return NewsletterChannel|null
     */
    public function findByChannelId(string $channelId): ?NewsletterChannel
    {
        return $this->findOneBy(['channelId' => $channelId]);
    }

    /**
     * Finds all newsletter channel records not matching the given list of newsletter channel IDs.
     *
     * @param string[] $channelIds
     *
     * @return QueryResultInterface<int, NewsletterChannel>
     *
     * @throws InvalidQueryException
     */
    public function findAllExceptWithChannelId(array $channelIds): QueryResultInterface
    {
        $query = $this->createQuery();

        return $query
            ->matching(
                $query->logicalNot(
                    $query->in(
                        'channel_id',
                        $channelIds
                    )
                )
            )
            ->execute();
    }
}
