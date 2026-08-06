import { expect, test } from '@playwright/test';

/**
 * Application shell smoke tests.
 *
 * @see specs/06-frontend.md, specs/12-ux-ui.md
 * @see CLAUDE.md — Playwright replaces Panther for E2E
 */

test('home page loads (smoke)', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle(/SysGC/);
});
