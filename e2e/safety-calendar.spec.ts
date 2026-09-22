import { expect, test } from '@playwright/test';

test('foreman can navigate the safety calendar and open a daily draft', async ({ page }) => {
  test.skip(!process.env.SAFETY_E2E_PROJECT_URL, 'Set SAFETY_E2E_PROJECT_URL to an authenticated project safety page.');

  await page.goto(process.env.SAFETY_E2E_PROJECT_URL!);
  await expect(page.getByRole('heading', { name: 'Registre de sécurité' })).toBeVisible();
  await expect(page.getByRole('grid', { name: /Calendrier de sécurité/ })).toBeVisible();
  await page.getByRole('link', { name: /Rapport à remplir|rapport manquant/i }).first().click();
  await expect(page.getByRole('heading', { name: 'Rapport de sécurité' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Enregistrer le brouillon' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Terminer le rapport' })).toBeVisible();
});
