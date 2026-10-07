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

namespace Netresearch\UniversalMessenger\ViewHelpers\Format;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Wraps a given value in curly braces to be used as placeholder inside the HTML
 * which gets passed to the Universal Messenger for further processing.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
class PlaceholderViewHelper extends AbstractViewHelper
{
    /**
     * Initialize arguments.
     *
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'value',
            'string',
            'The value to be used as placeholder. The one who gets wrapped in curly braces.',
            true,
        );
    }

    /**
     * @return string
     */
    public function render(): string
    {
        return '{' . $this->arguments['value'] . '}';
    }
}
