<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\DataProcessing;

use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * ControlStructureProcessor.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class ControlStructureProcessor implements DataProcessorInterface
{
    /**
     * Get rows of teasered elements and crop tt_content.bodytext
     *
     * @param ContentObjectRenderer $cObj                       The data of the content element or page
     * @param array                 $contentObjectConfiguration The configuration of Content Object
     * @param array                 $processorConfiguration     The configuration of this processor
     * @param array                 $processedData              Key/value store of processed data (e.g. to be passed to a Fluid View)
     *
     * @return array the processed data as key/value store
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        // Pass the flex form configuration to the content element template
        $processedData['flexformConfiguration'] = $this->getFlexFormConfiguration($processedData);

        return $processedData;
    }

    /**
     * @param array $processedData
     *
     * @return array
     */
    private function getFlexFormConfiguration(array $processedData): array
    {
        if (!empty($processedData['data']['pi_flexform'])) {
            return GeneralUtility::makeInstance(FlexFormService::class)
                ->convertFlexFormContentToArray($processedData['data']['pi_flexform']);
        }

        return [];
    }
}
