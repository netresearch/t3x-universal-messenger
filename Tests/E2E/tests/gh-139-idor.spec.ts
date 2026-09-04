import { test, expect } from '@playwright/test';
import { loginToBackend, gotoUniversalMessengerModule, getModuleFrame } from './helpers/typo3-backend';

/**
 * Real-browser regression test for GH-139: createAction() must reject a
 * submitted channel that does not match the page's own configured channel,
 * even when the backend user is separately permitted for that channel.
 *
 * Fixture (Tests/E2E/Fixtures/seed-content.php): page uid 10 is configured
 * for channel 101 ("E2E Own Channel"); the admin user is permitted for both
 * channel 101 AND channel 102 ("E2E Other Channel"), which has nothing to do
 * with this page. Extbase's __trustedProperties only signs which property
 * names a POST may set, not their values, so tampering the hidden
 * "newsletterChannel" field's VALUE from 101 to 102 does not invalidate the
 * request signature. This is the exact shape of the reported IDOR.
 */
test.describe('Universal Messenger - GH-139 IDOR regression', () => {
    test('rejects a submitted channel that does not match the page\'s own channel', async ({ page }) => {
        await loginToBackend(page);
        await gotoUniversalMessengerModule(page, 10);

        const moduleFrame = getModuleFrame(page);

        // The test-send form: hidden inputs "newsletterChannel" (the page's
        // own channel, 101) and "send" (value "test").
        const testForm = moduleFrame.locator('form').filter({
            has: page.locator('input[name="send"][value="test"]'),
        });
        await expect(testForm, 'test-send form not found in the module').toHaveCount(1);

        const channelInput = testForm.locator('input[name="newsletterChannel"]');
        await expect(channelInput).toHaveValue('101');

        // Tamper: submit channel 102 instead of the page's own 101. The
        // admin user IS permitted for 102, just not on this page.
        await channelInput.evaluate((el: HTMLInputElement) => {
            el.value = '102';
        });
        await testForm.evaluate((form: HTMLFormElement) => form.submit());

        await page.waitForLoadState('networkidle');

        await expect(
            moduleFrame.locator('.alert-danger, .alert-error'),
            'the tampered request must be rejected with the generic access-denied message',
        ).toContainText('You or your user group do not have the necessary rights to send this newsletter.');
    });

    test('accepts the page\'s own channel unmodified (positive control)', async ({ page }) => {
        await loginToBackend(page);
        await gotoUniversalMessengerModule(page, 10);

        const moduleFrame = getModuleFrame(page);

        const testForm = moduleFrame.locator('form').filter({
            has: page.locator('input[name="send"][value="test"]'),
        });
        await expect(testForm.locator('input[name="newsletterChannel"]')).toHaveValue('101');

        // Submit the page's OWN channel unmodified, through the exact same
        // path as the tamper test. Without this control, a guard collapsed
        // to "always reject" would still pass that test. This proves the
        // guard actually discriminates rather than rejecting everything.
        await testForm.evaluate((form: HTMLFormElement) => form.submit());
        await page.waitForLoadState('networkidle');

        // The E2E fixture's apiUrl is unreachable, so a legitimate request
        // proceeds past the guard and still fails further down, once it
        // reaches the webservice call, with a DIFFERENT alert. First confirm
        // processing actually continued at all (a positive anchor: without
        // it, a regression that silently swallows the request before any
        // flash message renders would leave the locator matching zero
        // elements and the negative assertion below would pass vacuously),
        // then confirm the guard's own access-denied message specifically
        // never appears.
        const resultAlert = moduleFrame.locator('.alert-danger, .alert-error');
        await expect(
            resultAlert,
            'processing must continue past the guard and produce some result',
        ).toBeVisible();
        await expect(
            resultAlert,
            'the guard must not reject the page\'s own, correctly-permitted channel',
        ).not.toContainText('You or your user group do not have the necessary rights to send this newsletter.');
    });
});
