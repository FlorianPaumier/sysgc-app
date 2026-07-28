import { expect, test } from '@playwright/test';

/**
 * Application shell — desktop sidebar vs. mobile bottom nav.
 *
 * The styleguide route (/_styleguide) renders the authenticated app layout
 * (templates/layouts/app.html.twig) plus the state components, so it is the
 * canonical test target for this card. Responsive visibility is the part that
 * only a real browser can verify (computed display per breakpoint).
 *
 * @see specs/06-frontend.md, specs/12-ux-ui.md ("Mobile layout")
 * @see CLAUDE.md — Playwright replaces Panther for E2E
 */

const SAFETY_ORANGE = 'rgb(244, 121, 32)'; // #F47920 — specs/12-ux-ui.md primary accent
const STYLEGUIDE = '/_styleguide';

test.describe('Application shell (desktop ≥ lg)', () => {
  test.use({ viewport: { width: 1280, height: 800 } });

  test('shows the desktop sidebar and hides the mobile bottom nav', async ({ page }) => {
    await page.goto(STYLEGUIDE);

    await expect(page.locator('.app-sidebar')).toBeVisible();
    await expect(page.locator('.bottom-nav')).toBeHidden();
  });

  test('CTA button uses the safety-orange accent', async ({ page }) => {
    await page.goto(STYLEGUIDE);

    const cta = page.locator('.btn-primary').first();
    await expect(cta).toBeVisible();
    await expect(cta).toHaveCSS('background-color', SAFETY_ORANGE);
  });

  test('secondary button uses the navy/ink token', async ({ page }) => {
    await page.goto(STYLEGUIDE);

    const secondary = page.locator('.btn-ghost').first();
    await expect(secondary).toBeVisible();
    // Ink/near-black (#17161A) — specs/12-ux-ui.md
    await expect(secondary).toHaveCSS('color', 'rgb(23, 22, 26)');
  });
});

test.describe('Application shell (mobile < lg)', () => {
  // iPhone-ish viewport, comfortably below the Tailwind `lg` (1024px) breakpoint.
  test.use({ viewport: { width: 390, height: 844 } });

  test('shows the bottom nav and hides the desktop sidebar', async ({ page }) => {
    await page.goto(STYLEGUIDE);

    await expect(page.locator('.bottom-nav')).toBeVisible();
    await expect(page.locator('.app-sidebar')).toBeHidden();
  });

  test('bottom nav has the three foreman buttons in the right order', async ({ page }) => {
    await page.goto(STYLEGUIDE);

    const labels = await page.locator('.bottom-nav__btn').allInnerTexts();
    expect(labels.map((t) => t.trim())).toEqual(['Sécurité', 'Heures', 'Conformité']);
  });

  test('bottom nav is full width and 56px tall', async ({ page }) => {
    await page.goto(STYLEGUIDE);

    const nav = page.locator('.bottom-nav');
    await expect(nav).toBeVisible();

    const box = await nav.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.height).toBeCloseTo(56, 0);
    expect(box!.width).toBeCloseTo(page.viewportSize()!.width, 0);
  });

  test('every bottom-nav button meets the 44×44px touch target', async ({ page }) => {
    await page.goto(STYLEGUIDE);

    const buttons = page.locator('.bottom-nav__btn');
    const count = await buttons.count();
    expect(count).toBe(3);

    for (let i = 0; i < count; i++) {
      const box = await buttons.nth(i).boundingBox();
      expect(box).not.toBeNull();
      expect(box!.height).toBeGreaterThanOrEqual(44);
      expect(box!.width).toBeGreaterThanOrEqual(44);
    }
  });

  test('renders the empty state with visible text', async ({ page }) => {
    await page.goto(STYLEGUIDE);

    await expect(page.locator('.empty-state')).toBeVisible();
    await expect(page.locator('.empty-state')).toContainText('Aucun chantier');
  });
});

test('home page loads (smoke)', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle(/SysGC/);
});
