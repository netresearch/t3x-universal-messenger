// SPDX-FileCopyrightText: Netresearch DTT GmbH
// SPDX-License-Identifier: LicenseRef-Netresearch-Restricted-Use

import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  fullyParallel: false, // Shared fixtures (one admin user, one page) - safer sequential
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 2 : undefined,
  timeout: 60_000,
  reporter: 'html',
  use: {
    baseURL: process.env.BASE_URL || 'https://universal-messenger.ddev.site',
    trace: 'on-first-retry',
    ignoreHTTPSErrors: true,
  },
  projects: [
    {
      // One backend login before the workers start: the first concurrent
      // logins of one user race in TYPO3's Registry::set()
      // (netresearch/t3x-rte_ckeditor_image#926).
      name: 'setup',
      testMatch: /.*\.setup\.ts/,
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      dependencies: ['setup'],
    },
  ],
});
