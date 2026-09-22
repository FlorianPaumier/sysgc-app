import { expect, test } from '@playwright/test';

test('an owner can add a subcontractor and see its RBQ verification state', async ({ page }) => {
  test.skip(!process.env.RBQ_E2E_PROJECT_URL, 'Set RBQ_E2E_PROJECT_URL to a project RBQ page authenticated through storage state.');

  await page.goto(process.env.RBQ_E2E_PROJECT_URL!);
  await expect(page.getByRole('heading', { name: 'Vérification RBQ' })).toBeVisible();
  await page.getByRole('link', { name: 'Ajouter' }).click();
  await page.getByLabel('Nom de l’entreprise').fill('E2E Construction');
  await page.getByLabel('Numéro de licence RBQ').fill('RBQ-1234-5678-90');
  await page.getByRole('button', { name: 'Ajouter' }).click();

  await expect(page.getByText('E2E Construction')).toBeVisible();
  await expect(page.getByText('Non vérifiée')).toBeVisible();
  await expect(page.locator('#rbq-batch-progress')).toHaveAttribute('aria-live', 'polite');
});
