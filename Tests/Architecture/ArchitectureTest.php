<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Architecture rules enforced via PHPat (runs inside PHPStan).
 *
 * The controller layer is the outermost layer: it may depend on services, domain
 * and view helpers, but nothing inner may depend back on a controller. The domain
 * layer is the innermost layer and must stay free of infrastructure concerns.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
final class ArchitectureTest
{
    private const NAMESPACE_ROOT = 'Netresearch\\UniversalMessenger';

    public function testViewHelpersDoNotDependOnControllers(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\ViewHelpers'))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Controller'))
            ->because('View helpers must not depend on controllers.');
    }

    public function testServicesDoNotDependOnControllers(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Service'))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Controller'))
            ->because('Services must not depend on controllers.');
    }

    public function testEventListenersDoNotDependOnControllers(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Backend\\EventListener'))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Controller'))
            ->because('Event listeners must not depend on controllers.');
    }

    public function testMiddlewareDoesNotDependOnControllers(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Middleware'))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Controller'))
            ->because('Middlewares must not depend on controllers.');
    }

    public function testCommandsDoNotDependOnControllers(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Command'))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Controller'))
            ->because('Console commands must not depend on controllers.');
    }

    public function testDomainDoesNotDependOnOuterLayers(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Domain'))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace(self::NAMESPACE_ROOT . '\\Controller'),
                Selector::inNamespace(self::NAMESPACE_ROOT . '\\Service'),
                Selector::inNamespace(self::NAMESPACE_ROOT . '\\Middleware'),
            )
            ->because('The domain layer must stay free of controller, service and middleware dependencies.');
    }
}
