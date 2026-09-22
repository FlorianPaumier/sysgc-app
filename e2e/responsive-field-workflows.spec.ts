import { expect, test } from '@playwright/test';
import path from 'node:path';

async function loadFixture(page: import('@playwright/test').Page): Promise<void> {
  await page.setContent(`
    <div class="app-shell"><div class="app-shell__main"><div class="app-body">
      <main class="terrain-dashboard">
        <p class="alert-banner" data-offline-status>Hors ligne — 1 modification en attente.</p>
        <section class="terrain-task-grid"><article class="terrain-task-card"><h2>Chantier Nord</h2><a class="btn btn-primary">Ouvrir le rapport</a></article></section>
        <div class="terrain-columns"><section class="terrain-section"><h2>Rapports manquants</h2><ul class="terrain-record-list"><li class="terrain-record"><strong>Chantier Nord</strong><span>Rapport non rempli</span><a>Ouvrir</a></li></ul></section><section class="terrain-section"><h2>Rapports complétés</h2></section></div>
      </main>
    </div></div></div>
    <section class="safety-daily"><div class="safety-checklist"><section class="safety-checklist__section"><div class="safety-photo-grid"><label class="safety-photo-add"><input type="file">Prendre une photo</label></div><canvas></canvas><button class="btn">Effacer</button><button class="btn">Valider</button></section><div class="safety-checklist__save"><button class="btn">Enregistrer</button><button class="btn btn-primary">Terminer</button></div></div></section>
    <nav class="bottom-nav bottom-nav--foreman"><a class="bottom-nav__btn">Sécurité</a><a class="bottom-nav__btn">Chantiers</a><a class="bottom-nav__btn">Compte</a></nav>`);
  await page.addStyleTag({ path: path.resolve('assets/styles/tokens.css') });
  await page.addStyleTag({ path: path.resolve('assets/styles/authenticated.css') });
}

test.describe('mobile field workflows', () => {
  test('keeps terrain and safety controls usable at 375px', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await loadFixture(page);

    await expect(page.locator('.terrain-task-grid')).toHaveCSS('grid-template-columns', '343px');
    await expect(page.locator('.terrain-columns')).toHaveCSS('grid-template-columns', '343px');
    await expect(page.locator('.terrain-record a')).toHaveCSS('min-height', '44px');
    await expect(page.locator('.bottom-nav__btn').first()).toHaveCSS('min-height', '64px');
    await expect(page.locator('.safety-checklist__save')).toHaveCSS('padding-bottom', '0px');
    await expect(page.locator('.safety-checklist__save .btn').first()).toHaveCSS('min-height', '56px');
    await expect(page.locator('.safety-checklist canvas')).toHaveCSS('touch-action', 'none');
  });

  test('reflows field workflows into tablet columns at 768px', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await loadFixture(page);

    await expect(page.locator('.terrain-task-grid')).toHaveCSS('grid-template-columns', '352px 352px');
    await expect(page.locator('.terrain-columns')).toHaveCSS('grid-template-columns', '352px 352px');
    await expect(page.locator('.safety-checklist__save')).toHaveCSS('display', 'flex');
    await expect(page.locator('.safety-checklist__save .btn').first()).toHaveCSS('min-width', '176px');
  });
});
