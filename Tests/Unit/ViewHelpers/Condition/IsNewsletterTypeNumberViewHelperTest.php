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

namespace Netresearch\UniversalMessenger\Tests\Unit\ViewHelpers\Condition;

use Netresearch\UniversalMessenger\Service\NewsletterRenderService;
use Netresearch\UniversalMessenger\ViewHelpers\Condition\IsNewsletterTypeNumberViewHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Tests that the condition holds only for a request of the newsletter page
 * type, given as POST data or as query parameter.
 *
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(IsNewsletterTypeNumberViewHelper::class)]
final class IsNewsletterTypeNumberViewHelperTest extends UnitTestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);

        parent::tearDown();
    }

    /**
     * @return array<string, array{array<string, mixed>|null, array<string, mixed>, bool}>
     */
    public static function requests(): array
    {
        $typeNumber = (string) NewsletterRenderService::VIEW_TYPE_NUMBER;

        return [
            'type number in the query'               => [null, ['type' => $typeNumber], true],
            'type number in the POST data'           => [['type' => $typeNumber], [], true],
            'POST data takes precedence'             => [['type' => '0'], ['type' => $typeNumber], false],
            'another type number'                    => [null, ['type' => '98'], false],
            'no type number'                         => [null, [], false],
            'type number given as a list in a query' => [null, ['type' => [$typeNumber]], false],
        ];
    }

    /**
     * @param array<string, mixed>|null $parsedBody
     * @param array<string, mixed>      $queryParams
     */
    #[Test]
    #[DataProvider('requests')]
    public function holdsOnlyForTheNewsletterTypeNumber(?array $parsedBody, array $queryParams, bool $expected): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.org/'))
            ->withParsedBody($parsedBody)
            ->withQueryParams($queryParams);

        self::assertSame(
            $expected,
            IsNewsletterTypeNumberViewHelper::verdict([], self::createStub(RenderingContextInterface::class)),
        );
    }
}
