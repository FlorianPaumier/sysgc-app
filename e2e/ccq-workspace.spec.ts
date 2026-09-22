import { expect, test } from '@playwright/test';
import { randomUUID } from 'node:crypto';

test('authenticated owner sees the organization-month CCQ workspace', async ({ page }) => {
  const email = `e2e-ccq-${randomUUID()}@example.com`;
  const mailpitUrl = process.env.SYSGC_MAILPIT_URL ?? 'http://127.0.0.1:8025';
  await page.goto('/inscription');
  await page.getByLabel(/courriel/i).fill(email);
  await page.locator('input[name*="organization"]').fill('Construction CCQ E2E');
  await page.locator('input[name*="rbq"]').fill('RBQ-1234-5678-90');
  await page.getByRole('button', { name: /créer|inscrire|commencer/i }).click();
  await expect(page).toHaveURL(/login\/check-email/);

  const deadline = Date.now() + 15_000;
  let verificationUrl = '';
  while (Date.now() < deadline && verificationUrl === '') {
    const inbox = await (await fetch(`${mailpitUrl}/api/v1/messages`)).json() as { messages: Array<{ ID: string; To: Array<{ Address: string }> }> };
    const message = inbox.messages.find(item => item.To.some(recipient => recipient.Address === email));
    if (message) {
      const detail = await (await fetch(`${mailpitUrl}/api/v1/message/${message.ID}`)).json() as { Text: string };
      verificationUrl = detail.Text.match(/https?:\/\/\S+\/connexion\/verifier\?\S+/)?.[0].replace('&amp;', '&') ?? '';
    }
    if (verificationUrl === '') await new Promise(resolve => setTimeout(resolve, 250));
  }
  expect(verificationUrl).not.toBe('');
  await page.goto(verificationUrl);
  await page.waitForURL('**/dashboard');
  await page.goto('/declarations');
  await expect(page.getByTestId('ccq-workspace')).toContainText('Déclaration CCQ');
  await expect(page.getByText(/Aucun chantier/i)).toBeVisible();
});
