// SPDX-FileCopyrightText: Netresearch DTT GmbH
// SPDX-License-Identifier: LicenseRef-Netresearch-Restricted-Use

import { test as setup } from '@playwright/test';
import { loginToBackend } from './helpers/typo3-backend';

/**
 * One backend login before the parallel workers start.
 *
 * Every backend login ends in BackendFormProtection::storeSessionTokenInRegistry(),
 * which calls Registry::set('core', 'formProtectionSessionToken:<uid>'). That
 * method counts the row and INSERTs it when the count is 0, without a lock, so
 * two first logins of the same user at the same moment both insert and the
 * second fails on the unique key sys_registry.entry_identifier: the login
 * answers 500 (netresearch/t3x-rte_ckeditor_image#926). Each spec file runs in
 * its own worker and logs in as its first step, so on a freshly provisioned
 * instance the first tests of both files race. Once the row exists, every
 * later login takes the UPDATE branch, which cannot collide.
 *
 * Each test still logs in by itself; nothing in the suite logs out, which is
 * what deletes the row.
 */
setup('create the form protection registry entry of the backend user', async ({ page }) => {
    await loginToBackend(page);
});
