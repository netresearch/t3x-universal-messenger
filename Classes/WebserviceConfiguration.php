<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger;

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
    private readonly string $apiBaseUrl;

    /**
     * The API key.
     *
     * @var string
     */
    private readonly string $apiKey;

    /**
     * WebserviceConfiguration constructor.
     */
    public function __construct(
        Configuration $configuration,
    ) {
        $this->apiBaseUrl = $configuration->getExtensionSetting('apiUrl') ?? '';
        $this->apiKey     = $configuration->getExtensionSetting('apiKey') ?? '';
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
