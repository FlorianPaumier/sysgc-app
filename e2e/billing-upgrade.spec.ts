import { test, expect } from '@playwright/test';

test.describe('Billing upgrade (Forfaits)', () => {
  const uniqueEmail = () => `e2e-billing-${Date.now()}@example.com`;

  test.beforeEach(async ({ page }) => {
    // Register a fresh org + auto-login.
    const email = uniqueEmail();
    await page.goto('https://localhost/inscription');
    await page.fill('input[type="email"]', email);
    await page.fill('input[name*="organization"]', 'E2E Billing Inc');
    await page.fill('input[name*="rbq"]', 'RBQ-1234-5678-90');
    await page.click('button[type="submit"]');

    // Navigate to the pricing page.
    await page.goto('https://localhost/forfaits');
  });

  test('shows three pricing cards with prices and worker limits', async ({ page }) => {
    await expect(page.locator('text=Starter')).toBeVisible();
    await expect(page.locator('text=Pro')).toBeVisible();
    await expect(page.locator('text=Business')).toBeVisible();

    // Prices
    await expect(page.locator('text=79 $')).toBeVisible();
    await expect(page.locator('text=149 $')).toBeVisible();
    await expect(page.locator('text=199 $')).toBeVisible();

    // Worker limits
    await expect(page.locator("text=Jusqu'a 5 travailleurs")).toBeVisible();
    await expect(page.locator("text=Jusqu'a 20 travailleurs")).toBeVisible();
    await expect(page.locator("text=Jusqu'a 50 travailleurs")).toBeVisible();
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
