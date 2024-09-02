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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * AbstractHtmlViewHelper.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
abstract class AbstractHtmlViewHelper extends AbstractViewHelper
{
    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * The view helper template to render.
     *
     * @var string
     */
    protected static string $viewHelperTemplate;

    /**
     * @return StandaloneView
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getTemplateObject(): StandaloneView
    {
        $setup = $this->getConfigurationManager()
            ->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);

        $layoutRootPaths   = [];
        $layoutRootPaths[] = GeneralUtility::getFileAbsFileName(
            'EXT:universal_messenger/Resources/Private/Layouts/ViewHelpers/'
        );

        if (isset($setup['plugin.']['tx_universalmessenger.']['view.']['layoutRootPaths.'])) {
            foreach ($setup['plugin.']['tx_universalmessenger.']['view.']['layoutRootPaths.'] as $layoutRootPath) {
                $layoutRootPaths[] = GeneralUtility::getFileAbsFileName(rtrim($layoutRootPath, '/') . '/ViewHelpers/');
            }
        }

        $partialRootPaths   = [];
        $partialRootPaths[] = GeneralUtility::getFileAbsFileName(
            'EXT:universal_messenger/Resources/Private/Partials/ViewHelpers/'
        );

        if (isset($setup['plugin.']['tx_universalmessenger.']['view.']['partialRootPaths.'])) {
            foreach ($setup['plugin.']['tx_universalmessenger.']['view.']['partialRootPaths.'] as $partialRootPath) {
                $partialRootPaths[] = GeneralUtility::getFileAbsFileName(rtrim($partialRootPath, '/') . '/ViewHelpers/');
            }
        }

        $templateRootPaths   = [];
        $templateRootPaths[] = GeneralUtility::getFileAbsFileName(
            'EXT:universal_messenger/Resources/Private/Templates/ViewHelpers/'
        );

        if (isset($setup['plugin.']['tx_universalmessenger.']['view.']['templateRootPaths.'])) {
            foreach ($setup['plugin.']['tx_universalmessenger.']['view.']['templateRootPaths.'] as $templateRootPath) {
                $templateRootPaths[] = GeneralUtility::getFileAbsFileName(rtrim($templateRootPath, '/') . '/ViewHelpers/');
            }
        }

        /** @var StandaloneView $view */
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setLayoutRootPaths($layoutRootPaths);
        $view->setPartialRootPaths($partialRootPaths);
        $view->setTemplateRootPaths($templateRootPaths);
        $view->setTemplate(static::$viewHelperTemplate);

        return $view;
    }

    /**
     * @return ConfigurationManagerInterface
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getConfigurationManager(): ConfigurationManagerInterface
    {
        /** @var ConfigurationManager $configurationManager */
        $configurationManager = GeneralUtility::getContainer()
            ->get(ConfigurationManager::class);

        return $configurationManager;
    }
}
