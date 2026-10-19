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

namespace Netresearch\UniversalMessenger\ViewHelpers\Html;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * ColumnViewHelper.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
class ColumnViewHelper extends AbstractHtmlViewHelper
{
    /**
     * The view helper template to render.
     *
     * @var string
     */
    protected static string $viewHelperTemplate = 'Html/Column';

    /**
     * Initialize arguments.
     *
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'class',
            'string',
            'The class to be assigned to the column table',
        );

        $this->registerArgument(
            'number',
            'int',
            'The current number of column',
        );

        $this->registerArgument(
            'totalNumber',
            'int',
            'The total number of columns in the row table',
        );
    }

    /**
     * @return string
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function render(): string
    {
        // Fluid hands over the declared types or NULL for an argument that
        // is not set.
        $classArgument       = $this->arguments['class'] ?? null;
        $numberArgument      = $this->arguments['number'] ?? null;
        $totalNumberArgument = $this->arguments['totalNumber'] ?? null;

        $view        = $this->getTemplateObject();
        $class       = ['columns', 'small-12'];
        $number      = is_int($numberArgument) ? $numberArgument : 1;
        $totalNumber = is_int($totalNumberArgument) ? $totalNumberArgument : 1;

        if (is_string($classArgument)) {
            $class = explode(' ', trim($classArgument));
        } else {
            $class[] = 'large-' . (12 / $totalNumber);

            if ($number === 1) {
                $class[] = 'first';
            }

            if ($number === $totalNumber) {
                $class[] = 'last';
            }
        }

        // Template
        $view
            ->assign('class', implode(' ', $class))
            ->assign('content', $this->buildRenderChildrenClosure()());

        return $view->render(self::$viewHelperTemplate);
    }
}
