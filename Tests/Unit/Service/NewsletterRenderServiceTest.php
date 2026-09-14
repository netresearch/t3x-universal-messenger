<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit\Service;

use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests NewsletterRenderService::isNewsletterContainerTemplateConfigured(), the upfront check
 * UniversalMessengerController::indexAction() uses to decide whether to embed the preview
 * iframe or show a flash message instead.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(NewsletterRenderService::class)]
final class NewsletterRenderServiceTest extends UnitTestCase
{
    /** Container template unset -> not configured. */
    #[Test]
    public function returnsFalseWhenTheContainerTemplateIsNotSet(): void
    {
        $configurationStub = $this->createConfigurationStub(null);

        $subject = $this->createSubject($configurationStub);

        self::assertFalse($subject->isNewsletterContainerTemplateConfigured());
    }

    /** Container template set to an empty string -> not configured. */
    #[Test]
    public function returnsFalseWhenTheContainerTemplateIsAnEmptyString(): void
    {
        $configurationStub = $this->createConfigurationStub('');

        $subject = $this->createSubject($configurationStub);

        self::assertFalse($subject->isNewsletterContainerTemplateConfigured());
    }

    /** Container template set to a real path -> configured. */
    #[Test]
    public function returnsTrueWhenTheContainerTemplateIsSet(): void
    {
        $configurationStub = $this->createConfigurationStub(
            'EXT:universal_messenger/Resources/Private/Templates/Page/ExampleNewsletter.html',
        );

        $subject = $this->createSubject($configurationStub);

        self::assertTrue($subject->isNewsletterContainerTemplateConfigured());
    }

    /**
     * Stubs Configuration so getTypoScriptSetting('view/templatePathAndFilename') returns the
     * given container-template value; any other path returns null. A call using the wrong path
     * is caught by returnsTrueWhenTheContainerTemplateIsSet(), which would then observe null
     * instead of the configured string and fail; the two false-case tests cannot tell a wrong
     * path from a correct one, since both yield a falsy result either way.
     */
    private function createConfigurationStub(?string $templatePathAndFilename): Configuration
    {
        $configurationStub = self::createStub(Configuration::class);
        $configurationStub
            ->method('getTypoScriptSetting')
            ->willReturnCallback(
                static fn (string $path): ?string => $path === 'view/templatePathAndFilename' ? $templatePathAndFilename : null,
            );

        return $configurationStub;
    }

    /** Builds the service under test with only Configuration behaviorally stubbed. */
    private function createSubject(Configuration $configuration): NewsletterRenderService
    {
        return new NewsletterRenderService(
            self::createStub(RequestFactory::class),
            self::createStub(SiteFinder::class),
            self::createStub(ViewFactoryInterface::class),
            $configuration,
        );
    }
}
