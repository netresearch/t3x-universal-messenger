<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\ViewHelpers\Condition;

use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractConditionViewHelper;

/**
 * IsNewsletterTypeNumberViewHelper.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class IsNewsletterTypeNumberViewHelper extends AbstractConditionViewHelper
{
    /**
     * @param array<string, mixed>      $arguments
     * @param RenderingContextInterface $renderingContext
     *
     * @return bool
     */
    public static function verdict(array $arguments, RenderingContextInterface $renderingContext): bool
    {
        return (int) (
            $GLOBALS['TYPO3_REQUEST']->getParsedBody()['type']
            ?? $GLOBALS['TYPO3_REQUEST']->getQueryParams()['type']
            ?? null
        ) === NewsletterRenderService::VIEW_TYPE_NUMBER;
    }
}
