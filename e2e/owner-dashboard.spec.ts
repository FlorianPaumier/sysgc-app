import { expect, test } from '@playwright/test';
import { registerOwner } from './helpers/auth';

test('owner creates a chantier and triages it from the risk-first dashboard', async ({ page }) => {
  await registerOwner(page);
  await expect(page.getByRole('heading', { name: 'Priorités du portefeuille' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Nouveau chantier' }).first()).toBeVisible();
  await expect(page.getByRole('link', { name: 'Archives' })).toBeVisible();

  await page.getByRole('link', { name: 'Nouveau chantier' }).first().click();
  await page.fill('[name="project[address]"]', '1250 rue du Port');
  await page.fill('[name="project[clientName]"]', 'Client E2E');
  await page.fill('[name="project[startDate]"]', '2026-09-15');
  await page.fill('[name="project[budget]"]', '125000');
  await page.click('button[type="submit"]');
  await page.goto('/dashboard');

  const project = page.locator('[data-dashboard-project]').filter({ hasText: '1250 rue du Port' });
  await expect(project).toBeVisible();
  await expect(project).toContainText('Actif');
  await expect(project).toContainText('Non conforme');
  await expect(page.locator('[data-dashboard-alerts]')).toContainText('1250 rue du Port');
  await expect(page.locator('[data-stat="active"]')).toContainText('1');

  await page.getByRole('link', { name: 'Archives' }).click();
  await expect(page).toHaveURL(/status=archived/);
  await expect(page.getByText('Aucun chantier archivé')).toBeAttached();
});
