import { expect, test } from '@playwright/test';
import { registerOwner } from './helpers/auth';

/**
 * Application shell tests — admin sidebar, breadcrumb, user menu, mobile nav.
 *
 * @see specs/06-frontend.md, specs/12-ux-ui.md
 * @see CLAUDE.md — Playwright replaces Panther for E2E
 */

let emailCounter = 0;

function uniqueEmail(): string {
  return `e2e-shell-${Date.now()}-${++emailCounter}@example.com`;
}

async function registerAndLandOnDashboard(page: import('@playwright/test').Page): Promise<void> {
  await registerOwner(page, 'E2E Shell Construction');
}

test('home page loads (smoke)', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle(/SysGC/);
});

test.describe('Authenticated app shell', () => {
  test('dashboard shows sidebar, breadcrumb, stat cards, no public chrome', async ({ page }) => {
    await registerAndLandOnDashboard(page);

    // Sidebar visible on desktop
    await expect(page.locator('aside.app-sidebar')).toBeVisible();
    await expect(page.locator('nav.app-sidebar__nav')).toBeVisible();
    await expect(page.locator('aside.app-sidebar')).toHaveAttribute('data-shell-role', 'owner');
    await expect(page.locator('.app-sidebar__role')).toContainText('Propriétaire');
    await expect(page.locator('.app-sidebar__subscription')).toContainText('Aucun forfait');
    await expect(page.locator('.app-sidebar__link[href="/declarations"]')).toBeVisible();

    // Breadcrumb in header
    await expect(page.locator('.breadcrumb')).toBeVisible();
    await expect(page.locator('.breadcrumb')).toContainText('Tableau de bord');

    // Stat cards
    await expect(page.locator('.stat-card')).toHaveCount(4);

    // Public nav and footer hidden
    await expect(page.locator('footer#contact')).toHaveCount(0);
    await expect(page.locator('nav.nav')).toHaveCount(0);
  });

  test('sidebar toggle collapses and persists across reload', async ({ page }) => {
    await registerAndLandOnDashboard(page);

    const sidebar = page.locator('aside.app-sidebar');
    const toggle = page.locator('.app-header__toggle');

    // Wait for sidebar visible
    await expect(sidebar).toBeVisible();

    // Collapse
    await toggle.click();
    await expect(sidebar).toHaveClass(/app-sidebar--collapsed/);

    // Reload — should still be collapsed (localStorage)
    await page.reload();
    await expect(sidebar).toHaveClass(/app-sidebar--collapsed/);

    // Expand again
    await toggle.click();
    await expect(sidebar).not.toHaveClass(/app-sidebar--collapsed/);
  });

  test('user menu shows identity and logout link', async ({ page }) => {
    await registerAndLandOnDashboard(page);

    await expect(page.locator('.app-sidebar__user')).toBeVisible();
    await expect(page.locator('.app-sidebar__user-name')).toBeVisible();
    await expect(page.locator('a.app-sidebar__logout[href="/deconnexion"]')).toBeVisible();
  });

  test.describe('mobile', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('owner bottom nav exposes the four office destinations', async ({ page }) => {
      await registerAndLandOnDashboard(page);

      // Sidebar hidden on mobile
      await expect(page.locator('aside.app-sidebar')).not.toBeVisible();

      // Bottom nav visible with 4 buttons
      await expect(page.locator('nav.bottom-nav')).toBeVisible();
      const buttons = page.locator('nav.bottom-nav .bottom-nav__btn');
      await expect(buttons).toHaveCount(4);
      await expect(buttons).toHaveText(['Tableau de bord', 'Chantiers', 'Conformité', 'Plus']);
      await expect(buttons.first()).toHaveAttribute('aria-current', 'page');
    });
  });
});
