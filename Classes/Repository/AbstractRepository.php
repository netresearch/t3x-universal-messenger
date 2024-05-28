<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Repository;

use Netresearch\UniversalMessenger\Service\UniversalMessengerService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Class AbstractRepository.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
abstract class AbstractRepository implements LoggerAwareInterface, SingletonInterface
{
    use LoggerAwareTrait;

    /**
     * The UM service instance.
     *
     * @var UniversalMessengerService
     */
    protected UniversalMessengerService $universalMessengerService;

    /**
     * AbstractRepository constructor.
     *
     * @param UniversalMessengerService $universalMessengerService
     */
    public function __construct(
        UniversalMessengerService $universalMessengerService
    ) {
        $this->universalMessengerService = $universalMessengerService;
    }
}
