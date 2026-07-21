<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Functional\Controller;

use Netresearch\UniversalMessenger\Controller\UniversalMessengerController;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Reflection\ReflectionService;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Verifies how Extbase itself classifies the arguments of the send action.
 *
 * Extbase maps and validates action arguments before it calls the action method,
 * so a required argument fails the request before the controller can decide
 * anything. `ActionController::initializeActionMethodArguments()` derives that
 * from the class schema:
 *
 *     $this->arguments->addNewArgument($name, $type, $parameter->isOptional() === false, ...)
 *
 * A missing channel therefore has to reach the controller — which answers with a
 * proper message — instead of dying with a RequiredArgumentMissingException.
 *
 * This runs against the real container on purpose: a unit test that calls the
 * action directly bypasses exactly the layer examined here.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
final class UniversalMessengerControllerArgumentsTest extends FunctionalTestCase
{
    /**
     * @var non-empty-string[]
     */
    protected array $testExtensionsToLoad = [
        'netresearch/universal-messenger',
    ];

    #[Test]
    public function extbaseTreatsTheChannelOfTheSendActionAsOptional(): void
    {
        $parameter = $this->get(ReflectionService::class)
            ->getClassSchema(UniversalMessengerController::class)
            ->getMethod('createAction')
            ->getParameter('newsletterChannel');

        self::assertTrue(
            $parameter->isOptional(),
            'Extbase must treat the channel as optional, otherwise a request without it fails'
            . ' with a RequiredArgumentMissingException before the controller can reject it.',
        );

        self::assertNull(
            $parameter->getDefaultValue(),
            'The default has to be null so the controller sees "no channel given".',
        );
    }
}
