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

namespace Netresearch\UniversalMessenger\Tests\Functional\DataProcessing;

use Netresearch\UniversalMessenger\DataProcessing\ControlStructureProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Tests that the data processor of the "control_structure" content element
 * passes the element's FlexForm settings, converted by the real TYPO3
 * FlexForm tools, to the template.
 *
 * @license LicenseRef-Netresearch-Restricted-Use
 *
 * @see    https://www.netresearch.de
 */
#[CoversClass(ControlStructureProcessor::class)]
final class ControlStructureProcessorTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'netresearch/universal-messenger',
    ];

    #[Test]
    public function addsTheConvertedFlexFormSettingsAndKeepsTheProcessedData(): void
    {
        $flexForm = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>'
            . '<T3FlexForms><data><sheet index="sDEF"><language index="lDEF">'
            . '<field index="settings.replacementBodyText"><value index="vDEF">Shown when no content matches</value></field>'
            . '</language></sheet></data></T3FlexForms>';

        $processedData = (new ControlStructureProcessor())->process(
            self::createStub(ContentObjectRenderer::class),
            [],
            [],
            [
                'data' => [
                    'uid'         => 7,
                    'pi_flexform' => $flexForm,
                ],
            ],
        );

        self::assertSame(
            ['settings' => ['replacementBodyText' => 'Shown when no content matches']],
            $processedData['flexformConfiguration'],
        );
        self::assertSame(7, $processedData['data']['uid']);
    }

    #[Test]
    public function addsAnEmptyConfigurationWhenTheElementHasNoFlexForm(): void
    {
        $processor = new ControlStructureProcessor();
        $cObj      = self::createStub(ContentObjectRenderer::class);

        self::assertSame(
            [],
            $processor->process($cObj, [], [], ['data' => ['pi_flexform' => '']])['flexformConfiguration'],
        );
        self::assertSame(
            [],
            $processor->process($cObj, [], [], [])['flexformConfiguration'],
        );
    }
}
