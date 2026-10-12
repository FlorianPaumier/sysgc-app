import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { registerOwner } from './helpers/auth';

test.setTimeout(60_000);


test('owner account actions include protection and deletion makes project creation read-only', async ({ page }) => {
  await registerOwner(page);
  await page.goto('/organisation');
  const form = page.locator('form[action="/organisation/suppression"]');
  await expect(form.locator('input[name="_token"]')).toHaveValue(/.+/);
  await form.getByRole('button', { name: 'Planifier la suppression' }).click();
  await page.waitForURL('**/organisation');
  const response = await page.goto('/chantiers/nouveau');
  expect(response?.status()).toBe(403);
  await page.goto('/organisation');
  await page.locator('form[action="/organisation/suppression/annuler"]').getByRole('button').click();
  await page.waitForURL('**/organisation');
  expect((await page.goto('/chantiers/nouveau'))?.status()).toBe(200);
});

test('daily CCQ edits validate and the worker export can be downloaded and filed', async ({ page }) => {
  const fixture = seedFixture();
  await page.goto(`/connexion/verifier?email=${encodeURIComponent(fixture.email)}&token=${fixture.token}`);
  await page.waitForURL('**/dashboard');
  await page.goto('/declarations');
  const monday = page.getByRole('spinbutton', { name: `Heures de Audit Worker, ${fixture.monday}`, exact: true });
  await monday.fill('16');
  await page.getByRole('button', { name: 'Valider', exact: true }).click();
  await expect(page.locator('#ccq-validation')).toContainText('day_hour_ceiling');
  await monday.fill('8');
  await page.getByRole('button', { name: 'Valider', exact: true }).click();
  await expect(page.locator('#ccq-validation')).toContainText('Déclaration valide');
  await page.getByRole('button', { name: 'Exporter la déclaration en PDF' }).click();
  await expect(page.getByRole('button', { name: 'Confirmer le dépôt manuel' })).toBeVisible({ timeout: 30_000 });
  await page.getByRole('button', { name: 'Confirmer le dépôt manuel' }).click();
  await page.getByRole('button', { name: 'Oui, confirmer le dépôt' }).click();
  await expect(page.getByRole('button', { name: 'Oui, confirmer le dépôt' })).toHaveCount(0);
  await page.reload();
  await expect(page.locator('[data-testid="ccq-workspace"] > .table-wrap tbody')).toContainText('filed');
  await page.locator('tbody').getByRole('link', { name: 'Voir' }).click();
  await page.waitForURL('**/declarations/*');
  const pdf = await page.request.get(`${new URL(page.url()).pathname}/pdf`);
  expect(pdf.ok()).toBeTruthy();
  expect(pdf.headers()['content-type']).toContain('application/pdf');
});

test('offline safety edits, photos and signatures survive synchronization', async ({ page, context }) => {
  const fixture = seedFixture();
  await page.goto(`/connexion/verifier?email=${encodeURIComponent(fixture.email)}&token=${fixture.token}`);
  await page.waitForURL('**/dashboard');
  await page.goto(`/chantiers/${fixture.projectId}/securite/${fixture.date}`);
  await page.locator('[data-model="weather"]').selectOption('Nuageux');
  await page.getByRole('button', { name: 'Enregistrer le brouillon' }).click();
  await expect(page.locator('[data-live-name-value="SafetyChecklist"]')).toHaveAttribute('data-live-props-value', /"weather":"Nuageux"/);
  await context.setOffline(true);
  await page.getByRole('button', { name: 'Casques de sécurité portés', exact: true }).click();
  await page.locator('[data-model="incidentReport"]').fill('Inspection hors ligne');
  await page.locator('[data-controller="safety-media"]').evaluate(element => {
    element.dispatchEvent(new CustomEvent('photo:uploaded', { bubbles: true, detail: { dataUrl: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLbtAAAAABJRU5ErkJggg==' } }));
    element.dispatchEvent(new CustomEvent('signature:added', { bubbles: true, detail: { workerName: 'Audit Worker', dataUrl: 'data:image/png;base64,audit-signature' } }));
  });
  await page.getByRole('button', { name: 'Enregistrer le brouillon' }).click();
  await expect(page.locator('[data-offline-queue-target="banner"]')).toContainText('en attente');
  await context.setOffline(false);
  await expect(page.locator('[data-offline-queue-target="banner"]')).toBeHidden({ timeout: 15_000 });
  await page.reload();
  await expect(page.getByRole('button', { name: 'Casques de sécurité portés', exact: true })).toHaveAttribute('aria-pressed', 'true');
  await expect(page.locator('[data-model="incidentReport"]')).toHaveValue('Inspection hors ligne');
  await expect(page.locator('.safety-photo-grid img')).toHaveCount(1);
  await expect(page.locator('.safety-checklist')).toContainText('Audit Worker');
});

function seedFixture(): { projectId: string; email: string; token: string; date: string; monday: string } {
  return JSON.parse(execFileSync('docker', ['compose', 'exec', '-T', 'php', 'php', 'e2e/helpers/audit_fixture.php'], { encoding: 'utf8' }));
}
