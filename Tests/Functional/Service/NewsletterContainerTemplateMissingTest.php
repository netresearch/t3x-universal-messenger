<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException;

/**
 * Locks in the real TYPO3Fluid behavior GH-141's fix depends on. NewsletterRenderService::
 * getView() always sets templateRootPaths (the "Backend/Templates/" directory, injected
 * unconditionally by ext_localconf.php's addTypoScript() for every classic site), while
 * templatePathAndFilename only comes from the opt-in "Example Newsletter Template" static
 * template and stays null without it, which is what a classic (non-Site-Set) site missing
 * that static template ends up with. This test reproduces that exact shape, a real root
 * path configured but no matching convention-based template file in it, rather than an
 * entirely empty configuration, so it fails again if a future TYPO3/Fluid version ever
 * changes this specific condition to a silent fallback instead of an exception, meaning
 * NewsletterPreviewController's catch would stop being reachable.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
final class NewsletterContainerTemplateMissingTest extends FunctionalTestCase
{
    /**
     * @var non-empty-string[]
     */
    protected array $testExtensionsToLoad = [
        'netresearch/universal-messenger',
    ];

    /** Reproduces the real TYPO3Fluid failure NewsletterPreviewController's catch depends on. */
    #[Test]
    public function templateRootPathsWithoutAMatchingConventionFileThrowsInvalidTemplateResourceException(): void
    {
        $this->expectException(InvalidTemplateResourceException::class);

        $request = new Request(
            (new ServerRequest())->withAttribute(
                'extbase',
                new ExtbaseRequestParameters(),
            ),
        );

        $view = $this->get(ViewFactoryInterface::class)->create(
            new ViewFactoryData(
                templateRootPaths: [
                    ExtensionManagementUtility::extPath('universal_messenger') . 'Resources/Private/Backend/Templates/',
                ],
                request: $request,
            ),
        );

        $view->render();
    }
}
