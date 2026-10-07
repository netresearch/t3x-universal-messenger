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

namespace Netresearch\UniversalMessenger\Tests\Unit\Service;

use Netresearch\UniversalMessenger\Service\NewsletterPreviewToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * A preview token is accepted only for the page it was issued for, with the key it was issued with.
 */
#[CoversClass(NewsletterPreviewToken::class)]
final class NewsletterPreviewTokenTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'unit-test-encryption-key-not-secret';
    }

    private function subject(): NewsletterPreviewToken
    {
        return new NewsletterPreviewToken(new HashService());
    }

    #[Test]
    public function acceptsTheTokenItIssuedForThePage(): void
    {
        self::assertTrue($this->subject()->isValid(10, $this->subject()->create(10)));
    }

    #[Test]
    public function refusesTheTokenOfAnotherPage(): void
    {
        self::assertFalse($this->subject()->isValid(11, $this->subject()->create(10)));
    }

    #[Test]
    public function refusesAnEmptyOrAlteredToken(): void
    {
        $token = $this->subject()->create(10);

        self::assertFalse($this->subject()->isValid(10, ''));
        self::assertFalse($this->subject()->isValid(10, substr($token, 0, -1) . ($token[-1] === '0' ? '1' : '0')));
    }

    #[Test]
    public function refusesAPageIdBelowOne(): void
    {
        self::assertFalse($this->subject()->isValid(0, $this->subject()->create(0)));
    }

    #[Test]
    public function aTokenIssuedWithAnotherEncryptionKeyIsRefused(): void
    {
        $token                                              = $this->subject()->create(10);
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'another-installation-key';

        self::assertFalse($this->subject()->isValid(10, $token));
    }
}
