import { test, expect } from '@playwright/test';
import { loginToBackend, gotoUniversalMessengerModule, getModuleFrame } from './helpers/typo3-backend';

/**
 * Real-browser regression test for GH-141: UniversalMessengerController::indexAction()
 * must check upfront whether the newsletter container template
 * (plugin.tx_universalmessenger.view.templatePathAndFilename) is configured, before
 * embedding the preview iframe. A classic (non-Site-Set) site missing the "Example
 * Newsletter Template" static template leaves that TypoScript setting unset, which
 * used to surface as a raw Fluid exception inside the iframe. It must instead show a
 * real TYPO3 flash message and leave the preview area empty.
 *
 * Fixture (Tests/E2E/Fixtures/seed-content.php): page uid 11 is a newsletter page
 * whose sys_template unsets the container-template TypoScript, reproducing the real
 * "static template never included" condition on both the frontend plugin and backend
 * module TypoScript branches.
 */
test.describe('Universal Messenger - GH-141 missing container template', () => {
    test('shows a flash message instead of embedding the preview iframe', async ({ page }) => {
        await loginToBackend(page);
        await gotoUniversalMessengerModule(page, 11);

        const moduleFrame = getModuleFrame(page);

        await expect(
            moduleFrame.locator('.alert-danger, .alert-error'),
            'a real TYPO3 flash message must explain the missing template',
        ).toContainText('Newsletter template is not configured for this site.');

        await expect(
            moduleFrame.locator('.iframe-container iframe'),
            'the preview iframe must not be embedded when the container template is missing',
        ).toHaveCount(0);
    });

    test('embeds the preview iframe when the container template is configured (positive control)', async ({ page }) => {
        await loginToBackend(page);
        await gotoUniversalMessengerModule(page, 10);

        const moduleFrame = getModuleFrame(page);

        // Page uid 10 (the GH-139 fixture page) has no sys_template override, so it
        // uses the site's normal TypoScript. Without this control, a regression that
        // always suppresses the iframe would still pass the test above.
        await expect(
            moduleFrame.locator('.iframe-container iframe'),
            'the preview iframe must still be embedded on a normally configured page',
        ).toHaveCount(1);
    });
});
