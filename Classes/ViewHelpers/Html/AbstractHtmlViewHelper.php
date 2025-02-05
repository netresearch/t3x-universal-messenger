<?php

/**
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\ViewHelpers\Html;

use Netresearch\UniversalMessenger\Configuration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
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
     * @var ViewFactoryInterface
     */
    private ViewFactoryInterface $viewFactory;

    /**
     * @var Configuration
     */
    private Configuration $configuration;

    /**
     * Constructor.
     *
     * @param ViewFactoryInterface $viewFactory
     * @param Configuration        $configuration
     */
    public function __construct(
        ViewFactoryInterface $viewFactory,
        Configuration $configuration,
    ) {
        $this->viewFactory   = $viewFactory;
        $this->configuration = $configuration;
    }

    /**
     * @return ViewInterface
     */
    protected function getTemplateObject(): ViewInterface
    {
        $templateRootPaths   = [];
        $templateRootPaths[] = GeneralUtility::getFileAbsFileName(
            'EXT:universal_messenger/Resources/Private/Templates/ViewHelpers/'
        );

        if ($this->configuration->hasTypoScriptSetting('view/templateRootPaths')) {
            foreach ($this->configuration->getTypoScriptSetting('view/templateRootPaths') as $templateRootPath) {
                $templateRootPaths[] = GeneralUtility::getFileAbsFileName(rtrim($templateRootPath, '/') . '/ViewHelpers/');
            }
        }

        $viewFactoryData = new ViewFactoryData(
            templateRootPaths: $templateRootPaths,
        );

        return $this->viewFactory
            ->create($viewFactoryData);
    }
}
