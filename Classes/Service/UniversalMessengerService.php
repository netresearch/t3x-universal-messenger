<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Service;

use Exception;
use Netresearch\Sdk\UniversalMessenger\Api;
use Netresearch\Sdk\UniversalMessenger\Exception\DetailedServiceException;
use Netresearch\Sdk\UniversalMessenger\Exception\ServiceException;
use Netresearch\Sdk\UniversalMessenger\UniversalMessenger;
use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\WebserviceConfiguration;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Class UniversalMessengerService.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class UniversalMessengerService implements SingletonInterface
{
    private readonly LoggerInterface $logger;

    /**
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * @var UniversalMessenger
     */
    private readonly UniversalMessenger $universalMessenger;

    /**
     * UniversalMessengerService constructor.
     *
     * @param LogManager              $logManager
     * @param Configuration           $configuration
     * @param WebserviceConfiguration $webserviceConfiguration
     */
    public function __construct(
        LogManager $logManager,
        Configuration $configuration,
        WebserviceConfiguration $webserviceConfiguration,
    ) {
        $this->configuration = $configuration;

        $this->logger = $this->isLoggingEnabled()
            ? $logManager->getLogger(self::class)
            : new NullLogger();

        $this->universalMessenger = new UniversalMessenger(
            $this->logger,
            $webserviceConfiguration->getApiBaseUrl(),
            $webserviceConfiguration->getApiKey()
        );
    }

    /**
     * @return bool
     */
    private function isLoggingEnabled(): bool
    {
        try {
            $enableLogging = $this->configuration->getExtensionSetting('enableLogging');
        } catch (Exception) {
            return false;
        }

        return (bool) $enableLogging;
    }

    /**
     * Returns the entry point to the DvinciApply webservice API.
     *
     * @return Api
     *
     * @throws DetailedServiceException
     */
    public function api(): Api
    {
        try {
            // Create new service instance
            return $this->universalMessenger->api();
        } catch (ServiceException $exception) {
            $this->logger->error(
                $exception->getMessage(),
                [
                    'exception' => $exception,
                ]
            );

            throw new DetailedServiceException('Failed to create service instance');
        }
    }
}
