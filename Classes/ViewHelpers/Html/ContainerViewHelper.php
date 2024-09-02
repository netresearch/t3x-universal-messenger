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
 * ContainerViewHelper.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class ContainerViewHelper extends AbstractHtmlViewHelper
{
    /**
     * The view helper template to render.
     *
     * @var string
     */
    protected static string $viewHelperTemplate = 'Html/Container';

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
            'The class to be assigned to the container table',
            false,
            'container float-center'
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
        // Template
        $view = $this->getTemplateObject()
            ->assign('class', $this->arguments['class'])
            ->assign('content', $this->buildRenderChildrenClosure()());

        return $view->render();
    }
}
