import { expect, test } from '@playwright/test';
import { randomUUID } from 'node:crypto';

const baseUrl = process.env.SYSGC_BASE_URL ?? 'https://localhost';
const mailpitUrl = process.env.SYSGC_MAILPIT_URL ?? 'http://127.0.0.1:8025';

async function registerOwner(page: import('@playwright/test').Page): Promise<void> {
  const email = `e2e-desktop-${randomUUID()}@example.com`;
  await page.goto(`${baseUrl}/inscription`);
  await page.getByLabel(/courriel/i).fill(email);
  await page.locator('input[name*="organization"]').fill('E2E Bureau Construction');
  await page.locator('input[name*="rbq"]').fill('RBQ-1234-5678-90');
  await page.getByRole('button', { name: /créer|inscrire|commencer/i }).click();
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

test.describe('desktop office workflows', () => {
  test.use({ viewport: { width: 1280, height: 900 } });

  test('keeps the sidebar grouped and dense tables scrollable with sticky identifiers', async ({ page }) => {
    await registerOwner(page);
    await page.goto(`${baseUrl}/chantiers`);
    await page.getByRole('link', { name: 'Nouveau chantier' }).first().click();
    await page.getByLabel('Adresse du chantier').fill('725 boulevard du Port');
    await page.getByLabel('Nom du client').fill('Ateliers du Nord');
    await page.getByLabel('Date de début').fill('2026-09-16');
    await page.locator('input[name="project[budget]"]').fill('245000');
    await page.getByRole('button', { name: 'Enregistrer le chantier' }).click();
    await expect(page).toHaveURL(/\/chantiers$/);

    await expect(page.locator('.app-sidebar__group')).toHaveCount(4);
    await expect(page.locator('.app-sidebar__label')).toHaveText(['Principal', 'Conformité', 'Équipe', 'Administration']);
    await expect(page.locator('.app-sidebar')).toHaveCSS('width', '256px');
    await expect(page.locator('.project-table-wrap')).toHaveCSS('overflow-x', 'auto');
    await expect(page.locator('.project-table thead th[data-sticky="identity"]')).toHaveCSS('position', 'sticky');
  });
});
