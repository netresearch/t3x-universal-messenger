<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessUnionReturnDocblockRector;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\TYPO313\v0\MigrateAddUserTSConfigToUserTsConfigFileRector;

$configure = require_once __DIR__ . '/../.build/vendor/netresearch/typo3-ci-workflows/config/rector/rector.php';

return static function (RectorConfig $rectorConfig) use ($configure): void {
    // Shared org base config: code-quality sets, rule skips, phpstan-rector.neon
    $configure($rectorConfig, __DIR__ . '/..');

    // paths() replaces the shared list — re-declared to keep Tests/ in scope,
    // which the shared $projectRoot default leaves out.
    $rectorConfig->paths(array_merge(
        [
            __DIR__ . '/../Classes',
            __DIR__ . '/../Configuration',
            __DIR__ . '/../Resources',
            __DIR__ . '/../Tests',
        ],
        glob(__DIR__ . '/../ext_*.php') ?: [],
    ));

    $rectorConfig->disableParallel();

    $rectorConfig->sets([
        Typo3LevelSetList::UP_TO_TYPO3_14,
    ]);

    $rectorConfig->skip([
        __DIR__ . '/../ext_*.sql',
        RemoveUselessUnionReturnDocblockRector::class,
        MigrateAddUserTSConfigToUserTsConfigFileRector::class,
    ]);
};
