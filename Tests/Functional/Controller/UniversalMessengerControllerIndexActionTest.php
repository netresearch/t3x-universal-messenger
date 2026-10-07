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

namespace Netresearch\UniversalMessenger\Tests\Functional\Controller;

use Netresearch\Sdk\UniversalMessenger\Exception\ServiceException;
use Netresearch\Sdk\UniversalMessenger\Model\NewsletterStatus;
use Netresearch\UniversalMessenger\Configuration;
use Netresearch\UniversalMessenger\Controller\AbstractBaseController;
use Netresearch\UniversalMessenger\Controller\UniversalMessengerController;
use Netresearch\UniversalMessenger\Domain\Model\NewsletterChannel;
use Netresearch\UniversalMessenger\Domain\Repository\NewsletterChannelRepository;
use Netresearch\UniversalMessenger\Repository\EventFileRepository;
use Netresearch\UniversalMessenger\Repository\NewsletterRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Core\Bootstrap;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Dispatches the backend module's index action the way TYPO3 does, through
 * the Extbase backend bootstrap, with a real backend user, site, page tree,
 * TypoScript and module template. Only the Universal Messenger webservice is
 * replaced: the repositories asking it for the dispatch status and sending
 * an event (the latter must never be called by this action).
 *
 * The assertions read the rendered module HTML: the flash message an editor
 * sees, the channel panel, the preview iframe and the language menu.
 *
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(UniversalMessengerController::class)]
#[CoversClass(AbstractBaseController::class)]
#[CoversClass(NewsletterChannelRepository::class)]
#[CoversClass(NewsletterChannel::class)]
#[CoversClass(Configuration::class)]
final class UniversalMessengerControllerIndexActionTest extends FunctionalTestCase
{
    /**
     * @var int
     */
    private const ROOT_PAGE_ID = 1;

    /**
     * @var int
     */
    private const NEWSLETTER_PAGE_ID = 2;

    /**
     * @var int
     */
    private const REGULAR_PAGE_ID = 3;

    /**
     * @var int
     */
    private const CHANNEL_UID = 1;

    protected array $testExtensionsToLoad = [
        'netresearch/universal-messenger',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'universal_messenger' => [
                'storagePageId' => '0',
            ],
        ],
    ];

    private ?NewsletterStatus $newsletterStatus = null;

    private ?ServiceException $statusException = null;

    /**
     * @var string[]
     */
    private array $requestedStatusEventIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createPageTree();
        $this->createSite();
        $this->createNewsletterChannel();
        $this->createBackendUser();
        $this->replaceWebserviceRepositories();
    }

    protected function tearDown(): void
    {
        // Leave no request behind for the next test case: the extbase
        // configuration manager falls back to it.
        unset($GLOBALS['TYPO3_REQUEST']);

        parent::tearDown();
    }

    #[Test]
    public function rejectsAPageThatIsNotANewsletterPage(): void
    {
        $html = $this->dispatchIndexAction(self::REGULAR_PAGE_ID);

        self::assertStringContainsString(
            '[INFO] Universal Messenger: Please select a page with the page type "Newsletter".',
            $html,
        );
        self::assertStringNotContainsString('<iframe', $html);
        self::assertSame([], $this->requestedStatusEventIds);
    }

    #[Test]
    public function explainsAMissingNewsletterTemplateInsteadOfEmbeddingThePreview(): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable('sys_template')
            ->update('sys_template', ['include_static_file' => ''], ['pid' => self::ROOT_PAGE_ID]);

        $html = $this->dispatchIndexAction(self::NEWSLETTER_PAGE_ID);

        self::assertStringContainsString(
            '[ERROR] Universal Messenger: Newsletter template is not configured for this site.',
            $html,
        );
        self::assertStringNotContainsString('<iframe', $html);
        self::assertStringContainsString('<h2>Camino CRM Demo Channel</h2>', $html);
    }

    #[Test]
    public function showsTheChannelAndThePreviewOfANewsletterPage(): void
    {
        $html = $this->dispatchIndexAction(self::NEWSLETTER_PAGE_ID);

        self::assertStringContainsString('<h2>Camino CRM Demo Channel</h2>', $html);
        self::assertStringContainsString('<h2>Camino Demo Newsletter</h2>', $html);
        self::assertMatchesRegularExpression(
            '#<iframe src="https://example\.org/[^"]*tx_universalmessenger_newsletterpreview[^"]*"#',
            $html,
        );
        self::assertSame(
            ['LIVE-CAMINO-EN-' . self::NEWSLETTER_PAGE_ID],
            $this->requestedStatusEventIds,
            'The dispatch status must be looked up under the live event ID of the page.',
        );
        self::assertStringNotContainsString('The newsletter has been sent', $html);
    }

    /**
     * The status flags reported by the webservice, in the order the module
     * checks them, and the message the editor sees for them.
     *
     * @return array<string, array{NewsletterStatus, string}>
     */
    public static function dispatchStates(): array
    {
        return [
            'accepted, not processed yet'  => [self::createNewsletterStatus(), 'The newsletter has been sent to Universal Messenger and is being processed.'],
            'failed'                       => [self::createNewsletterStatus(isFailed: true), 'Newsletter sending has failed.'],
            'stopped'                      => [self::createNewsletterStatus(isStopped: true), 'Newsletter sending has been stopped.'],
            'queued'                       => [self::createNewsletterStatus(inQueue: true), 'Newsletter is currently in the queue and sending has not started yet.'],
            'finished, one recipient'      => [self::createNewsletterStatus(isFinished: true, contacted: 1), 'The newsletter has been sent out: One user has been contacted.'],
            'finished, several recipients' => [self::createNewsletterStatus(isFinished: true, contacted: 1234), 'The newsletter has been sent out: 1234 users have been contacted.'],
        ];
    }

    #[Test]
    #[DataProvider('dispatchStates')]
    public function showsTheDispatchStatusReportedByTheWebservice(NewsletterStatus $newsletterStatus, string $expectedMessage): void
    {
        $this->newsletterStatus = $newsletterStatus;

        $html = $this->dispatchIndexAction(self::NEWSLETTER_PAGE_ID);

        self::assertStringContainsString($expectedMessage, $html);
        self::assertStringContainsString('<h2>Camino CRM Demo Channel</h2>', $html);
    }

    #[Test]
    public function showsNoDispatchStatusWhenTheWebserviceReportsAnError(): void
    {
        $this->newsletterStatus        = self::createNewsletterStatus(isFinished: true, contacted: 5);
        $this->newsletterStatus->error = 'Unknown event ID';

        $html = $this->dispatchIndexAction(self::NEWSLETTER_PAGE_ID);

        self::assertStringNotContainsString('The newsletter has been sent out', $html);
        self::assertStringContainsString('<h2>Camino CRM Demo Channel</h2>', $html);
    }

    #[Test]
    public function stillRendersTheModuleWhenTheStatusLookupFails(): void
    {
        $this->statusException = new ServiceException('The webservice is unavailable.');

        $html = $this->dispatchIndexAction(self::NEWSLETTER_PAGE_ID);

        self::assertStringNotContainsString('The newsletter has been sent', $html);
        self::assertStringContainsString('<h2>Camino CRM Demo Channel</h2>', $html);
        self::assertSame(['LIVE-CAMINO-EN-' . self::NEWSLETTER_PAGE_ID], $this->requestedStatusEventIds);
    }

    /**
     * A channel that may only be used once must not offer a second live
     * dispatch once the webservice knows about the first one.
     */
    #[Test]
    public function disablesTheLiveButtonOfASingleUseChannelThatWasAlreadyDispatched(): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable('tx_universalmessenger_domain_model_newsletterchannel')
            ->update(
                'tx_universalmessenger_domain_model_newsletterchannel',
                ['skip_used_id' => 1],
                ['uid' => self::CHANNEL_UID],
            );

        $html = $this->dispatchIndexAction(self::NEWSLETTER_PAGE_ID);

        self::assertDoesNotMatchRegularExpression('#<button type="button" class="btn btn-danger btn-lg" disabled="disabled">#', $html);

        $this->newsletterStatus = self::createNewsletterStatus(isFinished: true, contacted: 2);

        $html = $this->dispatchIndexAction(self::NEWSLETTER_PAGE_ID);

        self::assertMatchesRegularExpression('#<button type="button" class="btn btn-danger btn-lg" disabled="disabled">#', $html);
    }

    /**
     * The doc header offers a language menu once the page has a translation
     * in another language of the site.
     */
    #[Test]
    public function offersTheLanguagesOfTheTranslatedPageInTheDocHeader(): void
    {
        $html = $this->dispatchIndexAction(self::NEWSLETTER_PAGE_ID);

        self::assertStringNotContainsString('Deutsch', $html);

        $this->getConnectionPool()->getConnectionForTable('pages')->insert('pages', [
            'pid'              => self::ROOT_PAGE_ID,
            'doktype'          => 20,
            'title'            => 'Camino Demo Newsletter DE',
            'sys_language_uid' => 1,
            'l10n_parent'      => self::NEWSLETTER_PAGE_ID,
        ]);

        $html = $this->dispatchIndexAction(self::NEWSLETTER_PAGE_ID, 1);

        self::assertStringContainsString('English', $html);
        self::assertStringContainsString('Deutsch', $html);
        self::assertStringContainsString('<h2>Camino Demo Newsletter DE</h2>', $html);
    }

    private function dispatchIndexAction(int $pageId, int $language = 0): string
    {
        $this->requestedStatusEventIds = [];

        $module = $this->get(ModuleProvider::class)->getModule(
            'netresearch_universal_messenger',
            $GLOBALS['BE_USER'],
        );
        self::assertNotNull($module);

        $site = $this->get(SiteFinder::class)->getSiteByPageId($pageId);

        $serverRequest = (new ServerRequest(
            'https://example.org/typo3/module/netresearch/universal-messenger?id=' . $pageId,
            'GET',
        ))
            ->withQueryParams(['id' => (string) $pageId])
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('module', $module)
            ->withAttribute('route', new Route('/module/netresearch/universal-messenger', ['_identifier' => 'netresearch_universal_messenger', 'packageName' => 'netresearch/universal-messenger']))
            ->withAttribute('moduleData', ModuleData::createFromModule($module, ['language' => $language]))
            ->withAttribute('site', $site);

        $serverRequest = $serverRequest->withAttribute(
            'normalizedParams',
            NormalizedParams::createFromRequest($serverRequest),
        );

        $GLOBALS['TYPO3_REQUEST'] = $serverRequest;

        $response = $this->get(Bootstrap::class)->handleBackendRequest($serverRequest);

        self::assertSame(200, $response->getStatusCode());

        return (string) $response->getBody();
    }

    private static function createNewsletterStatus(
        bool $isFailed = false,
        bool $isStopped = false,
        bool $inQueue = false,
        bool $isFinished = false,
        int $contacted = 0,
    ): NewsletterStatus {
        $newsletterStatus             = new NewsletterStatus();
        $newsletterStatus->eventId    = 'LIVE-CAMINO-EN-' . self::NEWSLETTER_PAGE_ID;
        $newsletterStatus->isFailed   = $isFailed;
        $newsletterStatus->isStopped  = $isStopped;
        $newsletterStatus->inQueue    = $inQueue;
        $newsletterStatus->isFinished = $isFinished;
        $newsletterStatus->contacted  = $contacted;

        return $newsletterStatus;
    }

    private function createPageTree(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('pages');

        $connection->insert('pages', [
            'uid'             => self::ROOT_PAGE_ID,
            'pid'             => 0,
            'doktype'         => 1,
            'is_siteroot'     => 1,
            'title'           => 'Camino',
            'perms_everybody' => 31,
        ]);
        $connection->insert('pages', [
            'uid'                         => self::NEWSLETTER_PAGE_ID,
            'pid'                         => self::ROOT_PAGE_ID,
            'doktype'                     => 20,
            'title'                       => 'Camino Demo Newsletter',
            'universal_messenger_channel' => self::CHANNEL_UID,
        ]);
        $connection->insert('pages', [
            'uid'     => self::REGULAR_PAGE_ID,
            'pid'     => self::ROOT_PAGE_ID,
            'doktype' => 1,
            'title'   => 'About us',
        ]);

        // A classic sys_template root record: it loads the extension's default
        // TypoScript (module.tx_universalmessenger.view) and, as an integrator
        // would, the static "Example Newsletter Template".
        $this->getConnectionPool()->getConnectionForTable('sys_template')->insert('sys_template', [
            'pid'                 => self::ROOT_PAGE_ID,
            'root'                => 1,
            'clear'               => 3,
            'title'               => 'Camino',
            'include_static_file' => 'EXT:universal_messenger/Configuration/TypoScript/ExampleNewsletter/',
        ]);
    }

    private function createSite(): void
    {
        $this->get(SiteWriter::class)->write('camino', [
            'rootPageId' => self::ROOT_PAGE_ID,
            'base'       => 'https://example.org/',
            'languages'  => [
                [
                    'title'           => 'English',
                    'enabled'         => true,
                    'languageId'      => 0,
                    'base'            => '/',
                    'locale'          => 'en_US.UTF-8',
                    'flag'            => 'us',
                    'navigationTitle' => 'English',
                ],
                [
                    'title'           => 'Deutsch',
                    'enabled'         => true,
                    'languageId'      => 1,
                    'base'            => '/de/',
                    'locale'          => 'de_DE.UTF-8',
                    'flag'            => 'de',
                    'navigationTitle' => 'Deutsch',
                    'fallbackType'    => 'strict',
                ],
            ],
        ]);
    }

    private function createNewsletterChannel(): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable('tx_universalmessenger_domain_model_newsletterchannel')
            ->insert('tx_universalmessenger_domain_model_newsletterchannel', [
                'uid'        => self::CHANNEL_UID,
                'pid'        => 0,
                'channel_id' => 'crmDemoChannel',
                'title'      => 'Camino CRM Demo Channel',
            ]);
    }

    private function createBackendUser(): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_users')->insert('be_users', [
            'uid'                          => 1,
            'pid'                          => 0,
            'username'                     => 'editor',
            'password'                     => 'not-a-real-password-hash',
            'admin'                        => 1,
            'universal_messenger_channels' => (string) self::CHANNEL_UID,
        ]);

        $backendUser     = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    /**
     * Replaces the two repositories talking to the Universal Messenger
     * webservice before the container hands them to the controller.
     */
    private function replaceWebserviceRepositories(): void
    {
        $newsletterRepository = self::createStub(NewsletterRepository::class);
        $newsletterRepository
            ->method('getStatus')
            ->willReturnCallback(function (string $eventId): NewsletterStatus {
                $this->requestedStatusEventIds[] = $eventId;

                if ($this->statusException instanceof ServiceException) {
                    throw $this->statusException;
                }

                if (!$this->newsletterStatus instanceof NewsletterStatus) {
                    throw new ServiceException('No status known for ' . $eventId);
                }

                return $this->newsletterStatus;
            });

        $eventFileRepository = $this->createMock(EventFileRepository::class);
        $eventFileRepository
            ->expects(self::never())
            ->method('sendEventFile');

        $container = $this->getContainer();
        self::assertInstanceOf(SymfonyContainerInterface::class, $container);

        $container->set(NewsletterRepository::class, $newsletterRepository);
        $container->set(EventFileRepository::class, $eventFileRepository);
    }
}
