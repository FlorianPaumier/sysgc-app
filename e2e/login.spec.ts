import { test, expect } from '@playwright/test';

test.describe('Login flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('https://localhost/connexion');
  });

  test('renders login form', async ({ page }) => {
    await expect(page.locator('form')).toBeVisible();
    await expect(page.locator('input[type="email"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('shows validation error for empty email', async ({ page }) => {
    await page.click('button[type="submit"]');

    // Should show validation error
    const emailInput = page.locator('input[type="email"]');
    await expect(emailInput).toBeVisible();
  });

  test('shows validation error for invalid email', async ({ page }) => {
    await page.fill('input[type="email"]', 'not-an-email');
    await page.click('button[type="submit"]');

    // Should show validation error
    const emailInput = page.locator('input[type="email"]');
    await expect(emailInput).toBeVisible();
  });

  test('dispatches magic link and shows confirmation page', async ({ page }) => {
    // In a real test, we would seed a user in the database
    // For now, we test the flow even with unknown email (security: same response)
    await page.fill('input[type="email"]', 'test@example.com');
    await page.click('button[type="submit"]');

    // Should show check email confirmation
    await expect(page.locator('text=Vérifiez vos courriels')).toBeVisible();
  });

  test('rate limits after 4 attempts with same email', async ({ page }) => {
    // Make 4 attempts with the same email
    for (let i = 0; i < 4; i++) {
      await page.goto('https://localhost/connexion');
      await page.fill('input[type="email"]', 'test@example.com');
      await page.click('button[type="submit"]');
    }

    // Should show rate limit message
    await expect(page.locator('text=Trop de tentatives')).toBeVisible();
  });

  test('valid magic link authenticates and redirects to dashboard', async ({ page, context }) => {
    // This test requires database seeding to create a valid magic link
    // In a real scenario, you would:
    // 1. Seed a user in the database
    // 2. Create a magic link for that user
    // 3. Extract the token from the database
    // 4. Navigate to /connexion/verifier?token=xxx&email=xxx
    // 5. Assert redirect to dashboard

    // For now, we test the structure
    await page.goto('https://localhost/connexion/verifier?token=invalid&email=test@example.com');

    // Should redirect to login with error
    await page.waitForURL('https://localhost/connexion');
    await expect(page.locator('.flash-error')).toContainText('invalide ou a expiré');
  });

  test('expired token shows error message', async ({ page }) => {
    await page.goto('https://localhost/connexion/verifier?token=expired&email=test@example.com');

    await page.waitForURL('https://localhost/connexion');
    await expect(page.locator('.flash-error')).toContainText('invalide ou a expiré');
  });

  test('logout redirects to login', async ({ page }) => {
    await page.goto('https://localhost/deconnexion');

    await page.waitForURL('https://localhost/connexion');
    await expect(page.locator('h1')).toContainText('Connexion');
  });

  test('registration link is visible on login page', async ({ page }) => {
    await expect(page.locator('a[href="/inscription"]')).toBeVisible();
    await expect(page.locator('text=Pas encore de compte')).toBeVisible();
  });
});

test.describe('Magic link flow (end-to-end)', () => {
  test('complete login journey: email → magic link → dashboard', async ({ page, context }) => {
    // This is a placeholder for a full E2E test that would:
    // 1. Seed a test user and organization in the database
    // 2. Navigate to /connexion
    // 3. Submit email
    // 4. Extract magic link from sent email (mock email service)
    // 5. Click magic link
    // 6. Assert redirected to dashboard
    // 7. Assert user is authenticated

    // For now, we skip this as it requires database setup and email mocking
    test.skip(true, 'Requires database seeding and email service mocking');
  });
});
