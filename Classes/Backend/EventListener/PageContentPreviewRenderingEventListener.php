<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Backend\EventListener;

use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Event listener to create the preview for content element "control_structure".
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
final readonly class PageContentPreviewRenderingEventListener
{
    /**
     * @var FlexFormService
     */
    private FlexFormService $flexFormService;

    /**
     * Constructor.
     *
     * @param FlexFormService $flexFormService
     */
    public function __construct(FlexFormService $flexFormService)
    {
        $this->flexFormService = $flexFormService;
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

        if ($event->getRecord()['CType'] !== 'control_structure') {
            return;
        }

        $flexformData = $this->flexFormService
            ->convertFlexFormContentToArray($event->getRecord()['pi_flexform'] ?? '');

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
            {$event->getRecord()['bodytext']}
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
