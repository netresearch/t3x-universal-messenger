<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger;

use Exception;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Extension configuration helper.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class Configuration
{
    /**
     * The page type used for newsletter pages.
     *
     * @var int
     */
    private const PAGE_TYPE_NEWSLETTER = 20;

    /**
     * @var ConfigurationManagerInterface|null
     */
    private ?ConfigurationManagerInterface $configurationManager = null;

    /**
     * @var ExtensionConfiguration|null
     */
    private ?ExtensionConfiguration $extensionConfiguration = null;

    /**
     * Returns the TypoScript configuration manager.
     *
     * @return ConfigurationManagerInterface
     */
    private function getConfigurationManager(): ConfigurationManagerInterface
    {
        // We can't use constructor injection here as this will not work together with "ext_localconf.php"
        if (!$this->configurationManager instanceof ConfigurationManagerInterface) {
            $this->configurationManager = GeneralUtility::makeInstance(ConfigurationManagerInterface::class);
        }

        return $this->configurationManager;
    }

    /**
     * Returns the TypoScript configuration.
     *
     * @return mixed|null
     */
    private function getTypoScriptConfiguration(): mixed
    {
        return $this->getConfigurationManager()
            ->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK
            );
    }

    /**
     * Returns the extension configuration.
     *
     * @return ExtensionConfiguration
     */
    private function getExtensionConfiguration(): ExtensionConfiguration
    {
        // We can't use constructor injection here as this will not work together with "ext_localconf.php"
        if (!$this->extensionConfiguration instanceof ExtensionConfiguration) {
            $this->extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        }

        return $this->extensionConfiguration;
    }

    /**
     * Returns an extension setting by a given path.
     *
     * @param string $path Path to get the config for
     *
     * @return string|null
     */
    public function getExtensionSetting(string $path): ?string
    {
        try {
            return $this->getExtensionConfiguration()
                ->get('universal_messenger', $path);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Returns TRUE if the specified TypoScript setting is available.
     *
     * @param string $path Path to get the config for
     *
     * @return bool
     */
    public function hasTypoScriptSetting(string $path): bool
    {
        return ArrayUtility::isValidPath($this->getTypoScriptConfiguration(), $path);
    }

    /**
     * Returns an extensions TypoScript setting.
     *
     * @param string $path Path to get the config for
     *
     * @return mixed|null
     */
    public function getTypoScriptSetting(string $path): mixed
    {
        try {
            return ArrayUtility::getValueByPath($this->getTypoScriptConfiguration(), $path);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * @return int
     */
    public function getNewsletterPageDokType(): int
    {
        try {
            $pageType = (int) $this->getExtensionSetting('newsletterPageDokType');
        } catch (Exception) {
            $pageType = 0;
        }

        return ($pageType === 0) ? self::PAGE_TYPE_NEWSLETTER : $pageType;
    }
}
