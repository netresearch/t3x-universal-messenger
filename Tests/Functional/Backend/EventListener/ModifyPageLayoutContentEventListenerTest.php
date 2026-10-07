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

namespace Netresearch\UniversalMessenger\Tests\Functional\Backend\EventListener;

use Netresearch\UniversalMessenger\Backend\EventListener\ModifyPageLayoutContentEventListener;
use Netresearch\UniversalMessenger\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\Buttons\LinkButton;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Tests the "Open in Universal Messenger" button the listener adds to the
 * doc header of the page module: only on newsletter pages, pointing to the
 * module for the same page and the language selected in the page module.
 *
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(ModifyPageLayoutContentEventListener::class)]
#[CoversClass(Configuration::class)]
final class ModifyPageLayoutContentEventListenerTest extends FunctionalTestCase
{
    /**
     * @var int
     */
    private const NEWSLETTER_PAGE_ID = 2;

    /**
     * @var int
     */
    private const REGULAR_PAGE_ID = 3;

    protected array $testExtensionsToLoad = [
        'netresearch/universal-messenger',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $connection = $this->getConnectionPool()->getConnectionForTable('pages');
        $connection->insert('pages', [
            'uid'     => self::NEWSLETTER_PAGE_ID,
            'pid'     => 0,
            'doktype' => 20,
            'title'   => 'Camino Demo Newsletter',
        ]);
        $connection->insert('pages', [
            'uid'     => self::REGULAR_PAGE_ID,
            'pid'     => 0,
            'doktype' => 1,
            'title'   => 'About us',
        ]);

        $this->getConnectionPool()->getConnectionForTable('be_users')->insert('be_users', [
            'uid'      => 1,
            'pid'      => 0,
            'username' => 'editor',
            'password' => 'not-a-real-password-hash',
            'admin'    => 1,
        ]);

        $backendUser     = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    protected function tearDown(): void
    {
        // Leave no request behind for the next test case: the extbase
        // configuration manager falls back to it.
        unset($GLOBALS['TYPO3_REQUEST']);

        parent::tearDown();
    }

    #[Test]
    public function addsALinkToTheModuleForTheSamePageAndLanguageOnANewsletterPage(): void
    {
        $request = $this->createPageModuleRequest(['id' => (string) self::NEWSLETTER_PAGE_ID], 1);

        $button = $this->findModuleButton($this->dispatch($request), $request);

        self::assertInstanceOf(LinkButton::class, $button);
        self::assertSame('Open in Universal Messenger', $button->getTitle());
        self::assertTrue($button->getShowLabelText());
        self::assertSame('actions-file-view', $button->getIcon()?->getIdentifier());

        $href = $button->getHref();
        self::assertStringContainsString('/module/netresearch/universal-messenger', $href);
        self::assertStringContainsString('id=' . self::NEWSLETTER_PAGE_ID, $href);
        self::assertStringContainsString('language=1', $href);
    }

    /**
     * The page ID also arrives as POST data, e.g. after saving in the page
     * module; without module data the default language is linked.
     */
    #[Test]
    public function readsThePageFromThePostedDataAndFallsBackToTheDefaultLanguage(): void
    {
        $request = $this->createPageModuleRequest([], null)
            ->withParsedBody(['id' => (string) self::NEWSLETTER_PAGE_ID]);

        $button = $this->findModuleButton($this->dispatch($request), $request);

        self::assertInstanceOf(LinkButton::class, $button);
        self::assertStringContainsString('id=' . self::NEWSLETTER_PAGE_ID, $button->getHref());
        self::assertStringContainsString('language=0', $button->getHref());
    }

    #[Test]
    public function addsNoButtonToARegularPage(): void
    {
        $request = $this->createPageModuleRequest(['id' => (string) self::REGULAR_PAGE_ID], 0);

        self::assertNull($this->findModuleButton($this->dispatch($request), $request));
    }

    #[Test]
    public function addsNoButtonWithoutASelectedPage(): void
    {
        $request = $this->createPageModuleRequest([], 0);

        self::assertNull($this->findModuleButton($this->dispatch($request), $request));
    }

    private function dispatch(ServerRequest $request): ModuleTemplate
    {
        $GLOBALS['TYPO3_REQUEST'] = $request;

        $moduleTemplate = $this->get(ModuleTemplateFactory::class)->create($request);

        $listener = new ModifyPageLayoutContentEventListener(
            new Configuration(),
            $this->get(IconFactory::class),
            $this->get(UriBuilder::class),
            $this->get(ComponentFactory::class),
        );

        $listener(new ModifyPageLayoutContentEvent($request, $moduleTemplate));

        return $moduleTemplate;
    }

    /**
     * @param array<string, string> $queryParams
     */
    private function createPageModuleRequest(array $queryParams, ?int $language): ServerRequest
    {
        $request = (new ServerRequest('https://example.org/typo3/module/web/layout'))
            ->withQueryParams($queryParams)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('route', new Route('/module/web/layout', ['_identifier' => 'web_layout', 'packageName' => 'typo3/cms-backend']));

        if ($language !== null) {
            $request = $request->withAttribute('moduleData', new ModuleData('web_layout', ['language' => $language]));
        }

        return $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
    }

    /**
     * Returns the button the listener placed into the third group of the
     * left button bar, or NULL when there is none.
     */
    private function findModuleButton(ModuleTemplate $moduleTemplate, ServerRequest $request): ?LinkButton
    {
        $buttons = $moduleTemplate->getDocHeaderComponent()->getButtonBar()->getButtons($request);

        foreach ($buttons[ButtonBar::BUTTON_POSITION_LEFT][3] ?? [] as $button) {
            if ($button instanceof LinkButton
                && str_contains($button->getHref(), '/module/netresearch/universal-messenger')
            ) {
                return $button;
            }
        }

        return null;
    }
}
