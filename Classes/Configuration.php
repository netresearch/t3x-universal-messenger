<?php


/**
 * This file is part of the package netresearch/nrc-universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrcUniversalMessenger;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
     */
    private const PAGE_TYPE_NEWSLETTER = 20;

    /**
     * @return ExtensionConfiguration
     */
    private static function getExtensionConfiguration(): ExtensionConfiguration
    {
        return GeneralUtility::makeInstance(ExtensionConfiguration::class);
    }

    /**
     * @return int
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public static function getNewsletterPageDokType(): int
    {
        $pageType = (int) self::getExtensionConfiguration()
            ->get('nrc_universal_messenger', 'universalMessengerNewsletterPageDokType');

        return ($pageType === 0) ? self::PAGE_TYPE_NEWSLETTER : $pageType;
    }
}
