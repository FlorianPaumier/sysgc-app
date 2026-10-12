import { test, expect } from '@playwright/test';
import { registerOwner } from './helpers/auth';

test('owner account actions include protection and deletion makes project creation read-only', async ({ page }) => {
  await registerOwner(page);
  await page.goto('/organisation');
  const form = page.locator('form[action="/organisation/suppression"]');
  await expect(form.locator('input[name="_token"]')).toHaveValue(/.+/);
  await form.getByRole('button', { name: 'Planifier la suppression' }).click();
  await page.waitForURL('**/organisation');
  const response = await page.goto('/chantiers/nouveau');
  expect(response?.status()).toBe(403);
  await page.goto('/organisation');
  await page.locator('form[action="/organisation/suppression/annuler"]').getByRole('button').click();
  await page.waitForURL('**/organisation');
  expect((await page.goto('/chantiers/nouveau'))?.status()).toBe(200);
});
