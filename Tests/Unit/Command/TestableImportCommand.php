<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\UniversalMessenger\Tests\Unit\Command;

use Netresearch\UniversalMessenger\Command\ImportCommand;

/**
 * Skips resolving the real production collaborators via
 * GeneralUtility::makeInstance(), so a test can inject doubles onto the
 * parent's private properties (via reflection) without bootstrap()
 * immediately overwriting them.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
final class TestableImportCommand extends ImportCommand
{
    protected function bootstrap(): void {}
}
