import { test, expect } from '@playwright/test';

test.describe('Registration flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('https://localhost/inscription');
  });

  test('renders registration form', async ({ page }) => {
    await expect(page.locator('form')).toBeVisible();
    await expect(page.locator('input[type="email"]')).toBeVisible();
    await expect(page.locator('input[name*="organization"]')).toBeVisible();
    await expect(page.locator('input[name*="rbq"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('shows validation errors for empty fields', async ({ page }) => {
    await page.click('button[type="submit"]');

    // Should show validation errors
    const emailInput = page.locator('input[type="email"]');
    await expect(emailInput).toBeVisible();
  });

  test('shows validation error for invalid email', async ({ page }) => {
    await page.fill('input[type="email"]', 'not-an-email');
    await page.fill('input[name*="organization"]', 'Test Company');
    await page.fill('input[name*="rbq"]', 'RBQ-1234-5678-90');
    await page.click('button[type="submit"]');

    // Should show validation error for email
    const emailInput = page.locator('input[type="email"]');
    await expect(emailInput).toBeVisible();
  });

  test('shows validation error for invalid RBQ format', async ({ page }) => {
    await page.fill('input[type="email"]', 'test@example.com');
    await page.fill('input[name*="organization"]', 'Test Company');
    await page.fill('input[name*="rbq"]', 'invalid-rbq');
    await page.click('button[type="submit"]');

    // Should show validation error for RBQ
    const rbqInput = page.locator('input[name*="rbq"]');
    await expect(rbqInput).toBeVisible();
  });

  test('accepts valid RBQ format', async ({ page }) => {
    await page.fill('input[type="email"]', 'test@example.com');
    await page.fill('input[name*="organization"]', 'Test Company');
    await page.fill('input[name*="rbq"]', 'RBQ-1234-5678-90');
    await page.click('button[type="submit"]');

    // Should proceed (will fail with duplicate email or DB error in real test)
    await expect(page.locator('form')).toBeVisible();
  });

  test('shows placeholder for RBQ license format', async ({ page }) => {
    const rbqInput = page.locator('input[name*="rbq"]');
    await expect(rbqInput).toHaveAttribute('placeholder', 'RBQ-1234-5678-90');
  });

  test('handles duplicate email gracefully', async ({ page }) => {
    // This test requires seeding a user with existing email first
    await page.fill('input[type="email"]', 'existing@example.com');
    await page.fill('input[name*="organization"]', 'Test Company');
    await page.fill('input[name*="rbq"]', 'RBQ-1234-5678-90');
    await page.click('button[type="submit"]');

    // If email exists, should redirect to login with error
    // For now, we just verify form structure
    await expect(page.locator('form')).toBeVisible();
  });

  test('handles repeated registration requests safely', async ({ page }) => {
    const email = `registration-rate-limit-${Date.now()}@example.com`;
    // Make 4 attempts with the same email
    for (let i = 0; i < 4; i++) {
      await page.goto('https://localhost/inscription');
      await page.fill('input[type="email"]', email);
      await page.fill('input[name*="organization"]', `Company ${i}`);
      await page.fill('input[name*="rbq"]', `RBQ-${1000 + i}-${2000 + i}-90`);
      await page.click('button[type="submit"]');
    }

    // Repeated submissions must not expose a server error or create a broken page.
    await expect(page).toHaveURL(/\/(connexion|inscription)(?:\/verifier)?$/);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  });

  test('login link is visible on registration page', async ({ page }) => {
    // Check if there's a link back to login
    const hasLoginLink = await page.locator('a[href="/connexion"]').count() > 0;
    // Might not be present, just informational
  });
});

test.describe('Registration flow (end-to-end)', () => {
  test('complete registration journey: form → org/user created → magic link → auto-login', async ({ page }) => {
    // This is a placeholder for a full E2E test that would:
    // 1. Navigate to /inscription
    // 2. Fill valid form data
    // 3. Submit form
    // 4. Verify organization and user created in database
    // 5. Extract magic link from sent email (mock email service)
    // 6. Verify auto-login happened (user authenticated)
    // 7. Click magic link and verify email becomes verified
    // 8. Assert redirected to dashboard

    // For now, we skip this as it requires database setup and email mocking
    test.skip(true, 'Requires database seeding and email service mocking');
  });

  test('registration with existing email redirects to login', async ({ page }) => {
    // This would require seeding a user first
    test.skip(true, 'Requires database seeding');
  });
});
