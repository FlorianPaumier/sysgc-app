import { test, expect } from '@playwright/test';
import { registerOwner } from './helpers/auth';

test.describe('Organization settings', () => {
  const uniqueEmail = () => `e2e-org-${Date.now()}@example.com`;

  test.beforeEach(async ({ page }) => {
    // Register a fresh org + auto-login, then navigate to settings.
    await registerOwner(page, 'E2E Construction');
    await page.goto('https://localhost/organisation');
  });

  test('renders settings form prefilled with org data', async ({ page }) => {
    await expect(page.locator('form[name="organization_settings"]')).toBeVisible();
    await expect(page.locator('input[name="organization_settings[name]"]')).toHaveValue('E2E Construction');
    await expect(page.locator('input[name="organization_settings[rbqLicense]"]')).toHaveValue(/^RBQ-\d{4}-\d{4}-\d{2}$/);
    await expect(page.locator('form[name="organization_settings"] button[type="submit"]')).toBeVisible();
  });

  test('shows the plan gate when saving organization changes', async ({ page }) => {
    await page.fill('input[name="organization_settings[name]"]', 'Updated Construction');
    await page.locator('form[name="organization_settings"] button[type="submit"]').click();
    await expect(page.getByRole('status')).toContainText('Choisissez un forfait');
  });

  test('shows the plan gate when saving RBQ changes', async ({ page }) => {
    await page.fill('input[name="organization_settings[rbqLicense]"]', 'RBQ-2222-3333-44');
    await page.locator('form[name="organization_settings"] button[type="submit"]').click();
    await expect(page.getByRole('status')).toContainText('Choisissez un forfait');
  });

  test('shows plan section with placeholder before Stripe', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Forfait' })).toBeVisible();
    // Placeholder values: em-dash for unset tier.
    await expect(page.locator('text=Gérer mon abonnement')).toBeVisible();
  });
});
