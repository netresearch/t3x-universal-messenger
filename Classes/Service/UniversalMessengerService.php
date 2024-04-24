<?php

/**
 * This file is part of the package netresearch/nrc-universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrcUniversalMessenger\Service;

use Exception;
use Netresearch\NrcUniversalMessenger\WebserviceConfiguration;
use Netresearch\Sdk\UniversalMessenger\Api;
use Netresearch\Sdk\UniversalMessenger\Exception\DetailedServiceException;
use Netresearch\Sdk\UniversalMessenger\Exception\ServiceException;
use Netresearch\Sdk\UniversalMessenger\UniversalMessenger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
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
     * @var UniversalMessenger
     */
    private readonly UniversalMessenger $universalMessenger;

    /**
     * UniversalMessengerService constructor.
     *
     * @param LogManager              $logManager
     * @param ExtensionConfiguration  $extensionConfiguration
     * @param WebserviceConfiguration $webserviceConfiguration
     */
    public function __construct(
        LogManager $logManager,
        ExtensionConfiguration $extensionConfiguration,
        WebserviceConfiguration $webserviceConfiguration
    ) {
        $this->logger = $this->isLoggingEnabled($extensionConfiguration)
            ? $logManager->getLogger(self::class)
            : new NullLogger();

        $this->universalMessenger = new UniversalMessenger(
            $this->logger,
            $webserviceConfiguration->getApiBaseUrl(),
            $webserviceConfiguration->getApiKey()
        );
    }

    /**
     * @param ExtensionConfiguration $extensionConfiguration
     *
     * @return bool
     */
    private function isLoggingEnabled(ExtensionConfiguration $extensionConfiguration): bool
    {
        try {
            // Get extension configuration
            $settings = $extensionConfiguration->get('nrc_universal_messenger');
        } catch (Exception) {
            return false;
        }

        /** @var array{enable_logging: bool, ...} $settings */
        return isset($settings['enable_logging'])
            && $settings['enable_logging'];
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
