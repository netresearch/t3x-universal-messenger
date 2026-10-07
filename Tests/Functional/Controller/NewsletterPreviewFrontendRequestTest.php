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

use Netresearch\UniversalMessenger\Controller\NewsletterPreviewController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Requests the newsletter preview page type through the real frontend stack:
 * site routing, page arguments and the Extbase plugin. A request the preview
 * does not accept gets an empty 403, whatever plugin arguments it carries.
 *
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(NewsletterPreviewController::class)]
final class NewsletterPreviewFrontendRequestTest extends FunctionalTestCase
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
    private const PREVIEW_TYPE = 1715682913;

    protected array $testExtensionsToLoad = [
        'netresearch/universal-messenger',
    ];

    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'encryptionKey' => 'functional-test-encryption-key-not-secret',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $connection = $this->getConnectionPool()->getConnectionForTable('pages');
        $connection->insert('pages', [
            'uid'         => self::ROOT_PAGE_ID,
            'pid'         => 0,
            'doktype'     => 1,
            'is_siteroot' => 1,
            'title'       => 'Camino',
        ]);
        $connection->insert('pages', [
            'uid'     => self::NEWSLETTER_PAGE_ID,
            'pid'     => self::ROOT_PAGE_ID,
            'doktype' => 20,
            'title'   => 'Camino Demo Newsletter',
        ]);

        $this->getConnectionPool()->getConnectionForTable('sys_template')->insert('sys_template', [
            'pid'   => self::ROOT_PAGE_ID,
            'root'  => 1,
            'clear' => 3,
            'title' => 'Camino',
        ]);

        $this->get(SiteWriter::class)->write('camino', [
            'rootPageId' => self::ROOT_PAGE_ID,
            'base'       => 'https://example.org/',
            'languages'  => [
                [
                    'title'      => 'English',
                    'enabled'    => true,
                    'languageId' => 0,
                    'base'       => '/',
                    'locale'     => 'en_US.UTF-8',
                ],
            ],
        ]);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function refusedPluginArguments(): array
    {
        return [
            'no plugin arguments'  => [[]],
            'page id, no token'    => [['pageId' => (string) self::NEWSLETTER_PAGE_ID]],
            'page id, wrong token' => [['pageId' => (string) self::NEWSLETTER_PAGE_ID, 'token' => 'not-the-token']],
        ];
    }

    /**
     * @param array<string, string> $pluginArguments
     */
    #[Test]
    #[DataProvider('refusedPluginArguments')]
    public function answersARefusedPreviewRequestWithAnEmpty403(array $pluginArguments): void
    {
        $query = ['id' => (string) self::NEWSLETTER_PAGE_ID, 'type' => (string) self::PREVIEW_TYPE];

        foreach ($pluginArguments as $name => $value) {
            $query['tx_universalmessenger_newsletterpreview[' . $name . ']'] = $value;
        }

        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('https://example.org/'))->withQueryParameters($query),
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }
}
