<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Backend\EventListener;

use TYPO3\CMS\Lowlevel\Event\ModifyBlindedConfigurationOptionsEvent;

/**
 * Event listener to blind a configuration option an page "System" => "Configuration".
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
final class ModifyBlindedConfigurationOptionsEventListener
{
    /**
     * Invokes the event listener.
     *
     * @param ModifyBlindedConfigurationOptionsEvent $event
     *
     * @return void
     */
    public function __invoke(ModifyBlindedConfigurationOptionsEvent $event): void
    {
        $blindedConfigurationOptions = $event->getBlindedConfigurationOptions();

        // Blind API key
        if ($event->getProviderIdentifier() === 'confVars') {
            $blindedConfigurationOptions['TYPO3_CONF_VARS']['EXTENSIONS']['universal_messenger']['apiKey'] = '******';
        }

        $event->setBlindedConfigurationOptions($blindedConfigurationOptions);
    }
}
