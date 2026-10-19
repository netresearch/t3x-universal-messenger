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

$createConfig = require __DIR__ . '/../.Build/vendor/netresearch/typo3-ci-workflows/config/php-cs-fixer/config.php';

return $createConfig(
    <<<'EOF'
        This file is part of the package netresearch/universal-messenger.

        SPDX-FileCopyrightText: Netresearch DTT GmbH
        SPDX-License-Identifier: LicenseRef-Netresearch-Restricted-Use

        For the full copyright and license information, please read the
        LICENSE file that was distributed with this source code.
        EOF,
    __DIR__ . '/..',
);
