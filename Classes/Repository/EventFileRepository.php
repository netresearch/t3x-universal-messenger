<?php

/**
 * This file is part of the package netresearch/nrc-universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrcUniversalMessenger\Repository;

use Netresearch\Sdk\UniversalMessenger\Exception\DetailedServiceException;
use Netresearch\Sdk\UniversalMessenger\Request\Event;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Class EventFileRepository.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class EventFileRepository extends AbstractRepository
{
    /**
     * Sends an event file request.
     *
     * @param Event $request
     *
     * @return bool
     *
     * @throws DetailedServiceException
     * @throws ClientExceptionInterface
     */
    public function sendEventFile(Event $request): bool
    {
        return $this->universalMessengerService
            ->api()
            ->eventFile()
            ->event($request);
    }
}
