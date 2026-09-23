import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const publicPages = ['/', '/connexion', '/inscription', '/contact'];

for (const path of publicPages) {
  test(`public page ${path} has no critical accessibility violations`, async ({ page }) => {
    await page.goto(path);

    const results = await new AxeBuilder({ page })
      .exclude('.sf-toolbar')
      .analyze();

    expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([]);
  });
}

test('home page orange CTAs have readable foreground text', async ({ page }) => {
  await page.goto('/');

  const colors = await page.locator('.btn-primary, .plan.featured .plan-cta, .cta-form button').evaluateAll((elements) =>
    elements.map((element) => ({
      background: getComputedStyle(element).backgroundColor,
      color: getComputedStyle(element).color,
    })),
  );

  expect(colors).not.toHaveLength(0);
  for (const { background, color } of colors) {
    expect(background).not.toBe(color);
    expect(color).toMatch(/rgb\(23, 22, 26\)|rgb\(23 22 26/);
  }
});
