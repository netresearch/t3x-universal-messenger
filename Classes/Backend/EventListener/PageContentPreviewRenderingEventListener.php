<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Backend\EventListener;

use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Event listener to create the preview for content element "control_structure".
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
final readonly class PageContentPreviewRenderingEventListener
{
    /**
     * @var FlexFormTools
     */
    private FlexFormTools $flexFormTools;

    /**
     * Constructor.
     *
     * @param FlexFormTools $flexFormTools
     */
    public function __construct(FlexFormTools $flexFormTools)
    {
        $this->flexFormTools = $flexFormTools;
    }

    /**
     * Invokes the event listener.
     *
     * @param PageContentPreviewRenderingEvent $event
     */
    public function __invoke(PageContentPreviewRenderingEvent $event): void
    {
        if ($event->getTable() !== 'tt_content') {
            return;
        }

        $record = $event->getRecord();

        if ($record->getRecordType() !== 'control_structure') {
            return;
        }

        $flexformData = $this->flexFormTools
            ->convertFlexFormContentToArray($record->getRawRecord()?->get('pi_flexform') ?? '');

        $replacementBodyText = $flexformData['settings']['replacementBodyText'] ?? '';

        // Create preview output
        $event->setPreviewContent(
            <<<HTML
<div>
    <div>
        <div style="margin: 20px 0 10px 0;">
            <strong>{$this->translate('content_element.control_structure.bodytext')}</strong>
        </div>
        <div>
            {$record->get('bodytext')}
        </div>
    </div>
    <div>
        <div style="margin: 20px 0 10px 0;">
            <strong>{$this->translate('content_element.control_structure.flexform.replacementBodyText')}</strong>
        </div>
        <div>
            {$replacementBodyText}
        </div>
    </div>
</div>
HTML
        );
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private function translate(string $key): string
    {
        return LocalizationUtility::translate('LLL:EXT:universal_messenger/Resources/Private/Language/Backend.xlf:' . $key) ?? '';
    }
}
