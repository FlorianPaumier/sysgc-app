import { test, expect } from '@playwright/test';

test.describe('Organization settings', () => {
  const uniqueEmail = () => `e2e-org-${Date.now()}@example.com`;

  test.beforeEach(async ({ page }) => {
    // Register a fresh org + auto-login, then navigate to settings.
    const email = uniqueEmail();
    await page.goto('https://localhost/inscription');
    await page.fill('input[type="email"]', email);
    await page.fill('input[name*="organization"]', 'E2E Construction');
    await page.fill('input[name*="rbq"]', 'RBQ-1234-5678-90');
    await page.click('button[type="submit"]');

    // After auto-login, navigate to settings.
    await page.goto('https://localhost/organisation');
  });

  test('renders settings form prefilled with org data', async ({ page }) => {
    await expect(page.locator('form')).toBeVisible();
    await expect(page.locator('input[name="organization_settings[name]"]')).toHaveValue('E2E Construction');
    await expect(page.locator('input[name="organization_settings[rbqLicense]"]')).toHaveValue('RBQ-1234-5678-90');
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('updates organization name and shows success', async ({ page }) => {
    await page.fill('input[name="organization_settings[name]"]', 'Nouveau Nom Construction');
    await page.click('button[type="submit"]');

    await expect(page.locator('text=Vos modifications ont été enregistrées')).toBeVisible();
    await expect(page.locator('input[name="organization_settings[name]"]')).toHaveValue('Nouveau Nom Construction');
  });

  test('shows French validation error for invalid RBQ', async ({ page }) => {
    await page.fill('input[name="organization_settings[rbqLicense]"]', 'invalid-rbq');
    await page.click('button[type="submit"]');

    await expect(page.locator('input[name="organization_settings[rbqLicense]"].is-invalid')).toBeVisible();
  });

  test('shows plan section with placeholder before Stripe', async ({ page }) => {
    await expect(page.locator('text=Forfait')).toBeVisible();
    // Placeholder values: em-dash for unset tier.
    await expect(page.locator('text=Gérer mon abonnement')).toBeVisible();
  });
});
