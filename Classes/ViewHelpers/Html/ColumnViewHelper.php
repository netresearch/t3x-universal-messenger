<?php

/**
 * This file is part of the package netresearch/universal-messenger.
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
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
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
            'The class to be assigned to the column table'
        );

        $this->registerArgument(
            'number',
            'int',
            'The current number of column'
        );

        $this->registerArgument(
            'totalNumber',
            'int',
            'The total number of columns in the row table'
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
        $view        = $this->getTemplateObject();
        $class       = ['columns', 'small-12'];
        $number      = (int) ($this->arguments['number'] ?? 1);
        $totalNumber = (int) ($this->arguments['totalNumber'] ?? 1);

        if (isset($this->arguments['class'])) {
            $class = explode(' ', trim($this->arguments['class']));
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
