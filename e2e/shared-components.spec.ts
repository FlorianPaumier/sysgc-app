import { expect, test } from '@playwright/test';
import path from 'node:path';

test('shared feedback components use the paper tokens and remain usable on mobile', async ({ page }) => {
  await page.setContent(`
    <main>
      <header class="page-header"><div class="page-header__row"><div><h1>Déclarations</h1></div><a class="btn page-header__action">Créer</a></div></header>
      <span class="status-badge status-badge--warning">À surveiller</span>
      <span class="status-dot status-dot--success"><span class="status-dot__mark"></span><span>Conforme</span></span>
      <nav class="tab-bar"><ul class="tab-bar__list"><li><a class="tab-bar__link is-active">Aperçu</a></li></ul></nav>
      <nav class="month-selector"><a class="month-selector__button">←</a><time>septembre 2026</time><a class="month-selector__button">→</a></nav>
      <div class="loading-state">Chargement</div>
      <section class="error-state"><span class="error-state__mark">!</span><div><h3>Erreur</h3></div></section>
      <div class="toast toast--success"><p>Enregistré</p><button class="toast__close">×</button></div>
      <dialog class="modal" open aria-modal="true"><div class="modal__panel"><button class="modal__close">×</button></div></dialog>
    </main>`);
  await page.addStyleTag({ path: path.resolve('assets/styles/tokens.css') });
  await page.addStyleTag({ path: path.resolve('assets/styles/authenticated.css') });

  await expect(page.locator('.status-badge')).toHaveCSS('border-radius', '0px');
  await expect(page.locator('.status-badge')).toHaveCSS('background-color', 'rgb(255, 243, 214)');
  await expect(page.locator('.status-dot__mark')).toHaveCSS('background-color', 'rgb(22, 122, 62)');
  await expect(page.locator('.month-selector__button').first()).toHaveCSS('min-width', '44px');
  await expect(page.locator('.toast__close')).toHaveCSS('min-height', '44px');
  await expect(page.locator('.modal__close')).toHaveCSS('min-width', '44px');
  await expect(page.locator('.modal')).toHaveAttribute('aria-modal', 'true');
  await expect(page.locator('.page-header__row')).toHaveCSS('display', 'flex');
});
