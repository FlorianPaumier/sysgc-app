import { expect } from '@playwright/test';
import { randomUUID } from 'node:crypto';

export async function registerOwner(page: import('@playwright/test').Page, organization = 'E2E Construction'): Promise<void> {
  const email = `e2e-${randomUUID()}@example.com`;
  const rbq = `RBQ-${Math.floor(Math.random() * 9000 + 1000)}-${Math.floor(Math.random() * 9000 + 1000)}-${Math.floor(Math.random() * 90 + 10)}`;
  const mailpitUrl = process.env.SYSGC_MAILPIT_URL ?? 'http://127.0.0.1:8025';

  await page.goto('/inscription');
  await page.fill('input[type="email"]', email);
  await page.fill('input[name*="organization"]', organization);
  await page.fill('input[name*="rbq"]', rbq);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/login/check-email');

  const deadline = Date.now() + 15_000;
  let verificationUrl = '';
  while (Date.now() < deadline && verificationUrl === '') {
    const inbox = await (await fetch(`${mailpitUrl}/api/v1/messages`)).json() as { messages: Array<{ ID: string; To: Array<{ Address: string }> }> };
    const message = inbox.messages.find(item => item.To.some(recipient => recipient.Address === email));
    if (message) {
      const detail = await (await fetch(`${mailpitUrl}/api/v1/message/${message.ID}`)).json() as { Text: string };
      verificationUrl = detail.Text.match(/https?:\/\/\S+\/connexion\/verifier\?\S+/)?.[0].replace('&amp;', '&') ?? '';
    }
    if (!verificationUrl) await new Promise(resolve => setTimeout(resolve, 250));
  }

  expect(verificationUrl).not.toBe('');
  await page.goto(verificationUrl);
  await page.waitForURL('**/dashboard');
}
