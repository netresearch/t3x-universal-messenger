<?php

/**
 * This file is part of the package netresearch/nrc-universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrcUniversalMessenger;

use Exception;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Webservice configuration.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class WebserviceConfiguration
{
    /**
     * The base API URL used by the "apply" endpoint.
     *
     * @var string
     */
    private string $apiBaseUrl;

    /**
     * The API key.
     *
     * @var string
     */
    private string $apiKey;

    /**
     * WebserviceConfiguration constructor.
     *
     * @throws RuntimeException
     */
    public function __construct(
        ExtensionConfiguration $extensionConfiguration
    ) {
        try {
            $configuration = $extensionConfiguration->get('nrc_universal_messenger');
        } catch (Exception) {
            $configuration = [];
        }

        $this->apiBaseUrl = $configuration['universalMessengerApiUrl'] ?? '';
        $this->apiKey     = $configuration['universalMessengerApiKey'] ?? '';
    }

    /**
     * @return string
     */
    public function getApiBaseUrl(): string
    {
        return $this->apiBaseUrl;
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }
}
