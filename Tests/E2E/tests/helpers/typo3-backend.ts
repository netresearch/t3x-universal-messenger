import { Page, FrameLocator, expect } from '@playwright/test';

/**
 * Shared helpers for TYPO3 backend E2E tests.
 */

export const BACKEND_USER = process.env.TYPO3_BACKEND_USER || 'admin';
export const BACKEND_PASSWORD = process.env.TYPO3_BACKEND_PASSWORD || 'Joh316!!';
export const BASE_URL = process.env.BASE_URL || 'https://universal-messenger.ddev.site';

/**
 * Navigate to a page with retry logic for infrastructure readiness.
 *
 * In CI, the PHP-FPM/Apache container may not be fully ready when the first
 * request arrives, causing proxy errors (502/503). Retries up to 3 times.
 */
export async function gotoWithRetry(page: Page, path: string): Promise<void> {
    const url = `${BASE_URL}${path}`;
    const maxAttempts = 3;

    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        let response;
        try {
            response = await page.goto(url, { timeout: 30000 });
        } catch (error) {
            if (attempt < maxAttempts) {
                await page.waitForTimeout(2000);
                continue;
            }
            throw error;
        }
        await page.waitForLoadState('networkidle');

        const status = response?.status() ?? 0;
        if (status >= 200 && status < 500) {
            return;
        }

        if (attempt < maxAttempts) {
            await page.waitForTimeout(2000);
        }
    }

    throw new Error(`${url} still returning errors after ${maxAttempts} attempts`);
}

/** Login to the TYPO3 backend, or no-op if already logged in. */
export async function loginToBackend(page: Page): Promise<void> {
    await gotoWithRetry(page, '/typo3/');

    const loginForm = page.locator(
        'form[name="loginform"], #typo3-login-form, input[name="username"], #t3-username',
    );
    const isLoginPage = (await loginForm.count()) > 0;

    if (!isLoginPage) {
        return;
    }

    const usernameInput = page.locator('input[name="username"], #t3-username').first();
    const passwordInput = page.locator('input[name="p_field"], input[name="password"], #t3-password').first();

    await usernameInput.fill(BACKEND_USER);
    await passwordInput.fill(BACKEND_PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle', { timeout: 30000 });

    const backendIndicators = page.locator(
        '.modulemenu, .typo3-module-menu, [data-modulemenu], .scaffold, typo3-backend-sidebar-toggle',
    );
    await expect(
        backendIndicators.first(),
        'TYPO3 backend did not render after login',
    ).toBeVisible({ timeout: 30000 });
}

/** The TYPO3 backend module content iframe (its src carries a per-request token). */
export function getModuleFrame(page: Page): FrameLocator {
    return page.frameLocator('#typo3-contentIframe');
}

/** Navigate to the Universal Messenger module for a given page. */
export async function gotoUniversalMessengerModule(page: Page, pageId: number): Promise<void> {
    await gotoWithRetry(page, `/typo3/module/netresearch/universal-messenger?id=${pageId}`);
}
