<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Repository;

use Netresearch\Sdk\UniversalMessenger\Exception\AuthenticationException;
use Netresearch\Sdk\UniversalMessenger\Exception\DetailedServiceException;
use Netresearch\Sdk\UniversalMessenger\Exception\ServiceException;
use Netresearch\Sdk\UniversalMessenger\Model\Collection\NewsletterChannelCollection;
use Netresearch\Sdk\UniversalMessenger\Model\NewsletterStatus;

/**
 * Class NewsletterRepository.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class NewsletterRepository extends AbstractRepository
{
    /**
     * Finds all configured newsletter channels.
     *
     * @return NewsletterChannelCollection
     *
     * @throws AuthenticationException
     * @throws DetailedServiceException
     * @throws ServiceException
     */
    public function findAllChannels(): NewsletterChannelCollection
    {
        return $this->universalMessengerService
            ->api()
            ->newsletter()
            ->channels();
    }

    /**
     * Get newsletter status.
     *
     * @return NewsletterStatus
     *
     * @throws AuthenticationException
     * @throws DetailedServiceException
     * @throws ServiceException
     */
    public function getStatus(string $eventId): NewsletterStatus
    {
        return $this->universalMessengerService
            ->api()
            ->newsletter()
            ->status($eventId);
    }
}
