import { test, expect } from '@playwright/test';
import { registerOwner } from './helpers/auth';

test.describe('Billing upgrade (Forfaits)', () => {
  const uniqueEmail = () => `e2e-billing-${Date.now()}@example.com`;

  test.beforeEach(async ({ page }) => {
    // Register a fresh org + auto-login.
    await registerOwner(page, 'E2E Billing Inc');

    // Navigate to the pricing page.
    await page.goto('https://localhost/forfaits');
  });

  test('shows three pricing cards with prices and worker limits', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Starter' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Pro' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Business' })).toBeVisible();

    // Prices
    await expect(page.locator('.plan-card__price').nth(0)).toContainText('79');
    await expect(page.locator('.plan-card__price').nth(1)).toContainText('149');
    await expect(page.locator('.plan-card__price').nth(2)).toContainText('199');

    // Worker limits
    await expect(page.getByText('Jusqu’à 5 travailleurs')).toBeVisible();
    await expect(page.getByText('Jusqu’à 20 travailleurs')).toBeVisible();
    await expect(page.getByText('Jusqu’à 50 travailleurs')).toBeVisible();
  });

  test('selecting a tier submits and redirects towards Stripe', async ({ page }) => {
    // Intercept the POST and prevent real Stripe redirect.
    // Instead, verify the form submission intent.
    let submitted = false;
    await page.route('**/forfaits', async (route) => {
      const request = route.request();
      if (request.method() === 'POST') {
        submitted = true;
        // Redirect to local success page instead of Stripe.
        await route.fulfill({
          status: 302,
          headers: { Location: 'https://localhost/facturation/succes' },
        });
      } else {
        await route.continue();
      }
    });

    // Click the button for the Starter plan.
    const starterButton = page.locator('button:has-text("Commencer")');
    await starterButton.click();

    // Verify the form was submitted.
    expect(submitted).toBe(true);

    // Should land on the success page.
    await expect(page).toHaveURL('https://localhost/facturation/succes');
    await expect(page.locator('text=Merci')).toBeVisible();
    await expect(page.locator("text=Votre abonnement est actif")).toBeVisible();
  });
});
