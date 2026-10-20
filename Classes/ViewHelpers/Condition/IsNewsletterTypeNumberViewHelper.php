<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 * SPDX-License-Identifier: LicenseRef-Netresearch-Restricted-Use
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\ViewHelpers\Condition;

use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractConditionViewHelper;

/**
 * IsNewsletterTypeNumberViewHelper.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
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
        $serverRequest = $GLOBALS['TYPO3_REQUEST'];
        assert($serverRequest instanceof ServerRequestInterface);

        $parsedBody = $serverRequest->getParsedBody();
        $type       = (is_array($parsedBody) ? $parsedBody['type'] ?? null : null)
            ?? $serverRequest->getQueryParams()['type']
            ?? null;

        // A type that is not scalar (e.g. "type[]=…") is no type number
        return is_scalar($type)
            && ((int) $type === NewsletterRenderService::VIEW_TYPE_NUMBER);
    }
}
