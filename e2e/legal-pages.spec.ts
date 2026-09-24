import { expect, test } from '@playwright/test';

test('a visitor can reach both legal pages from the public footer', async ({ page }) => {
  await page.goto('/');

  await page.locator('footer a', { hasText: 'Confidentialité' }).click();
  await expect(page).toHaveURL(/\/confidentialite$/);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Politique de confidentialité');
  await expect(page.getByRole('heading', { name: 'Vos droits' })).toBeVisible();

  await page.locator('footer a', { hasText: 'Conditions' }).click();
  await expect(page).toHaveURL(/\/conditions-utilisation$/);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Conditions d’utilisation');
  await expect(page.getByText(/n’est ni affilié.*CCQ.*RBQ.*CNESST/i)).toBeVisible();
});
