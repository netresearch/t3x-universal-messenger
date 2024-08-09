<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\ViewHelpers\Html;

use Closure;
use Exception;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * ContainerViewHelper
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
     * @param array $arguments
     * @param Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     *
     * @return string
     *
     * @throws Exception
     */
    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        // Template
        $view = parent::getTemplateObject()
            ->assign('class', $arguments['class'])
            ->assign('content', $renderChildrenClosure());

        return $view->render();
    }
}
