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
     * Get rows of elements and add flex form configuration to processed data.
     *
     * @param ContentObjectRenderer $cObj                       The data of the content element or page
     * @param array                 $contentObjectConfiguration The configuration of Content Object
     * @param array                 $processorConfiguration     The configuration of this processor
     * @param array                 $processedData              Key/Value store of processed data (e.g., to be passed to a Fluid View)
     *
     * @return array The processed data as key/value store
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData,
    ): array {
        // Pass the flex form configuration to the content element template
        $processedData['flexformConfiguration'] = $this->convertFlexFormContentToArray(
            $processedData['data']['pi_flexform'] ?? ''
        );

        return $processedData;
    }

    /**
     * Returns the FlexForm service instance.
     *
     * @return FlexFormService
     */
    private function getFlexFormService(): FlexFormService
    {
        return GeneralUtility::makeInstance(FlexFormService::class);
    }

    /**
     * Converts the flex form data from XML to array.
     *
     * @param string $flexFormContent
     *
     * @return array<string, array<string, string>>
     */
    private function convertFlexFormContentToArray(string $flexFormContent): array
    {
        if ($flexFormContent !== '') {
            return $this->getFlexFormService()
                ->convertFlexFormContentToArray($flexFormContent);
        }

        return [];
    }
}
