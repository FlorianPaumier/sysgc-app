import { expect, test } from '@playwright/test';
import { randomUUID } from 'node:crypto';

const baseUrl = process.env.SYSGC_BASE_URL ?? 'https://localhost';
const mailpitUrl = process.env.SYSGC_MAILPIT_URL ?? 'http://127.0.0.1:8025';

async function registerOwner(page: import('@playwright/test').Page): Promise<void> {
  const email = `e2e-project-${randomUUID()}@example.com`;
  await page.goto(`${baseUrl}/inscription`);
  await page.getByLabel(/courriel/i).fill(email);
  await page.locator('input[name*="organization"]').fill('E2E Projets Construction');
  await page.locator('input[name*="rbq"]').fill('RBQ-1234-5678-90');
  await page.getByRole('button', { name: /créer|inscrire|commencer/i }).click();
  await page.waitForURL('**/login/check-email');

  const deadline = Date.now() + 15_000;
  let verificationUrl = '';
  while (Date.now() < deadline && verificationUrl === '') {
    const response = await fetch(`${mailpitUrl}/api/v1/messages`);
    const inbox = await response.json() as { messages: Array<{ ID: string; To: Array<{ Address: string }> }> };
    const message = inbox.messages.find(item => item.To.some(recipient => recipient.Address === email));
    if (message) {
      const detailResponse = await fetch(`${mailpitUrl}/api/v1/message/${message.ID}`);
      const detail = await detailResponse.json() as { Text: string };
      verificationUrl = detail.Text.match(/https?:\/\/\S+\/connexion\/verifier\?\S+/)?.[0].replace('&amp;', '&') ?? '';
    }
    if (verificationUrl === '') {
      await new Promise(resolve => setTimeout(resolve, 250));
    }
  }

  expect(verificationUrl, `magic link for ${email}`).not.toBe('');
  await page.goto(verificationUrl);
  await page.waitForURL('**/dashboard', { timeout: 15_000 });
}

test('owner creates a project, navigates its five tabs, then archives it', async ({ page }) => {
  await registerOwner(page);
  await page.goto(`${baseUrl}/chantiers`);

  await page.getByRole('link', { name: 'Nouveau chantier' }).first().click();
  await page.getByLabel('Adresse du chantier').fill('825 rue du Port');
  await page.getByLabel('Nom du client').fill('Ateliers du Nord');
  await page.getByLabel('Date de début').fill('2026-09-16');
  await page.locator('input[name="project[budget]"]').fill('245000');
  await page.getByLabel('État du chantier').selectOption('on_hold');
  await page.getByRole('button', { name: 'Enregistrer le chantier' }).click();

  await expect(page).toHaveURL(/\/chantiers$/);
  await expect(page.getByRole('link', { name: '825 rue du Port', exact: true })).toBeVisible();
  await expect(page.getByText('En pause').first()).toBeVisible();
  await page.getByRole('link', { name: /825 rue du Port/ }).first().click();

  const tabs = page.locator('.tab-bar__link');
  await expect(tabs).toHaveCount(5);
  await expect(tabs).toHaveText(['Aperçu', 'Conformité', 'Sécurité', 'Finances', 'Documents']);
  await page.getByRole('link', { name: 'Conformité', exact: true }).click();
  await expect(page).toHaveURL(/tab=conformite/);
  await expect(page.locator('turbo-frame#project-detail')).toContainText('Dossiers réglementaires');

  await page.getByRole('link', { name: 'Aperçu', exact: true }).click();
  await page.getByRole('button', { name: 'Archiver le chantier' }).click();
  await expect(page).toHaveURL(/\/chantiers$/);
  await expect(page.getByText('825 rue du Port')).toHaveCount(0);
  await page.getByRole('link', { name: 'Archives', exact: true }).click();
  await expect(page.getByRole('link', { name: '825 rue du Port', exact: true })).toBeVisible();
  await expect(page.getByText('Archivé').first()).toBeVisible();
  await page.getByRole('link', { name: '825 rue du Port', exact: true }).click();
  await expect(page.locator('[data-project-read-only]')).toContainText('lecture seule');
  await expect(page.getByRole('link', { name: /Modifier/ })).toHaveCount(0);
  await expect(page.getByRole('button', { name: /Archiver/ })).toHaveCount(0);
});

test.describe('mobile project management', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test('list uses project cards and the form keeps full-width touch actions', async ({ page }) => {
    await registerOwner(page);
    await page.goto(`${baseUrl}/chantiers/nouveau`);

    await expect(page.locator('.project-form')).toBeVisible();
    await expect(page.locator('.project-form__section')).toHaveCount(3);
    const submit = page.getByRole('button', { name: 'Enregistrer le chantier' });
    expect(await submit.evaluate(button => {
      const actions = button.parentElement;
      if (!actions) return false;
      const style = getComputedStyle(actions);
      const available = actions.clientWidth - Number.parseFloat(style.paddingLeft) - Number.parseFloat(style.paddingRight);
      return Math.abs(button.getBoundingClientRect().width - available) < 1;
    })).toBe(true);
    await expect(submit).toHaveCSS('min-height', '44px');

    await page.getByLabel('Adresse du chantier').fill('12 rue Mobile');
    await page.getByLabel('Nom du client').fill('Client Terrain');
    await page.getByLabel('Date de début').fill('2026-09-16');
    await page.locator('input[name="project[budget]"]').fill('75000');
    await submit.click();
    await expect(page).toHaveURL(/\/chantiers$/);
    await expect(page.locator('.project-table-wrap')).not.toBeVisible();
    await expect(page.locator('.project-grid--mobile')).toBeVisible();
    await expect(page.locator('.project-grid--mobile')).toContainText('12 rue Mobile');
  });
});
