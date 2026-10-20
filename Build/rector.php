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

use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveParentDelegatingClassMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessUnionReturnDocblockRector;
use Ssch\TYPO3Rector\TYPO313\v0\MigrateAddUserTSConfigToUserTsConfigFileRector;

$configure = require_once __DIR__ . '/../.Build/vendor/netresearch/typo3-ci-workflows/config/rector/rector.php';

return static function (RectorConfig $rectorConfig) use ($configure): void {
    // Shared org base config: paths (Classes/, Configuration/, Resources/,
    // Tests/, ext_*.php), code-quality sets, rule skips, the package's
    // phpstan-rector.neon, and the PHP and TYPO3 level sets for the lowest
    // supported versions (composer.json: php ^8.2, typo3/cms-core ^14.3).
    // With typo3: 14 the shared config also skips
    // MigratePluginContentElementAndPluginSubtypesRector.
    $configure($rectorConfig, __DIR__ . '/..', [
        'tests' => true,
        'php'   => '8.2',
        'typo3' => 14,
    ]);

    $rectorConfig->disableParallel();

    $rectorConfig->skip([
        RemoveUselessUnionReturnDocblockRector::class,
        MigrateAddUserTSConfigToUserTsConfigFileRector::class,
        // False positive (both rules): they can't tell a delegating override apart
        // from one that exists solely to widen the parent method's visibility.
        // TestableUniversalMessengerController::getChannelAuthorizationFailure()
        // widens the parent's protected method to public so unit tests can call it
        // directly (the file's own established pattern); the identical-looking body
        // is not dead code, and the visibility difference is the whole point.
        RemoveParentDelegatingClassMethodRector::class => [
            __DIR__ . '/../Tests/Unit/Controller/TestableUniversalMessengerController.php',
        ],
        MakeInheritedMethodVisibilitySameAsParentRector::class => [
            __DIR__ . '/../Tests/Unit/Controller/TestableUniversalMessengerController.php',
        ],
    ]);
};
