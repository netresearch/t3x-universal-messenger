<?php

/*
 * This file is part of the package netresearch/universal-messenger.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use a9f\Fractor\Configuration\FractorConfiguration;
use a9f\Typo3Fractor\Set\Typo3LevelSetList;

return FractorConfiguration::configure()
    ->withPaths(
        [
            __DIR__ . '/../Classes',
            __DIR__ . '/../Configuration',
            __DIR__ . '/../Resources',
            __DIR__ . '/../Tests',
        ],
    )
    ->withSets(
        [
            Typo3LevelSetList::UP_TO_TYPO3_14,
        ],
    );
