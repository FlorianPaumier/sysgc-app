import { expect, test } from '@playwright/test';
import { randomUUID } from 'node:crypto';

const mailpitUrl = process.env.SYSGC_MAILPIT_URL ?? 'http://127.0.0.1:8025';

async function registerOwner(page: import('@playwright/test').Page): Promise<void> {
  const email = `e2e-dashboard-${randomUUID()}@example.com`;
  await page.goto('/inscription');
  await page.fill('input[type="email"]', email);
  await page.fill('input[name*="organization"]', 'Portfolio E2E');
  await page.fill('input[name*="rbq"]', 'RBQ-1234-5678-90');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/login/check-email');

  const deadline = Date.now() + 15_000;
  let verificationUrl = '';
  while (Date.now() < deadline && verificationUrl === '') {
    const inbox = await (await fetch(`${mailpitUrl}/api/v1/messages`)).json() as {
      messages: Array<{ ID: string; To: Array<{ Address: string }> }>;
    };
    const message = inbox.messages.find(item => item.To.some(recipient => recipient.Address === email));
    if (message) {
      const detail = await (await fetch(`${mailpitUrl}/api/v1/message/${message.ID}`)).json() as { Text: string };
      verificationUrl = detail.Text.match(/https?:\/\/\S+\/connexion\/verifier\?\S+/)?.[0].replace('&amp;', '&') ?? '';
    }
    if (verificationUrl === '') {
      await new Promise(resolve => setTimeout(resolve, 250));
    }
  }

  expect(verificationUrl, `magic link for ${email}`).not.toBe('');
  await page.goto(verificationUrl);
  await page.waitForURL('**/dashboard', { waitUntil: 'domcontentloaded' });
}

test('owner creates a chantier and triages it from the risk-first dashboard', async ({ page }) => {
  await registerOwner(page);
  await expect(page.getByRole('heading', { name: 'Priorités du portefeuille' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Nouveau chantier' })).toBeVisible();
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
  await expect(page.getByText('Aucun chantier archivé')).toBeVisible();
});
