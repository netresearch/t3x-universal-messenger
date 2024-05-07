<?php

/**
 * This file is part of the package netresearch/nrc-universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrcUniversalMessenger\Domain\Repository;

use Netresearch\NrcUniversalMessenger\Domain\Model\NewsletterChannel;
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
 * @template T of NewsletterChannel
 *
 * @extends  Repository<T>
 */
class NewsletterChannelRepository extends Repository
{
    /**
     * Initializes the repository.
     *
     * @return void
     */
    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);

        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Finds all newsletter channel records not matching the given list of newsletter channel IDs.
     *
     * @param string[] $channelIds
     *
     * @return QueryResultInterface
     *
     * @throws InvalidQueryException
     */
    public function findAllNotByChannelId(array $channelIds): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalNot(
                $query->in(
                    'channel_id',
                    $channelIds
                )
            )
        );

        return $query->execute();
    }
}
