import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { Application } from '@hotwired/stimulus';
import ModalController from '../assets/controllers/modal_controller.js';
import ToastController from '../assets/controllers/toast_controller.js';
import TabBarController from '../assets/controllers/tab_bar_controller.js';
import NetworkStateController from '../assets/controllers/network_state_controller.js';

function installDom(html) {
  const dom = new JSDOM(html, { url: 'https://sysgc.test', pretendToBeVisual: true });
  globalThis.window = dom.window;
  globalThis.document = dom.window.document;
  globalThis.Element = dom.window.Element;
  globalThis.HTMLElement = dom.window.HTMLElement;
  globalThis.HTMLDialogElement = dom.window.HTMLDialogElement;
  globalThis.Node = dom.window.Node;
  globalThis.CustomEvent = dom.window.CustomEvent;
  globalThis.KeyboardEvent = dom.window.KeyboardEvent;
  globalThis.MouseEvent = dom.window.MouseEvent;
  globalThis.MutationObserver = dom.window.MutationObserver;
  return dom;
}

async function start(identifier, Controller) {
  const application = Application.start();
  application.register(identifier, Controller);
  await new Promise((resolve) => window.setTimeout(resolve, 0));
  return application;
}

test('toast can be dismissed and automatically leaves the live region', async () => {
  const dom = installDom('<div data-controller="toast" data-toast-duration-value="10"><button data-action="toast#dismiss">Fermer</button></div>');
  const application = await start('toast', ToastController);
  const toast = document.querySelector('[data-controller="toast"]');

  await new Promise((resolve) => window.setTimeout(resolve, 20));
  assert.equal(toast.isConnected, false);

  application.stop();
  dom.window.close();
});

test('modal opens for its id, closes explicitly, and restores trigger focus', async () => {
  const dom = installDom(`
    <button id="trigger">Ouvrir</button>
    <dialog id="confirm" data-controller="modal" data-action="modal:open@window->modal#open click->modal#backdropClose cancel->modal#close">
      <div data-modal-target="panel"><button data-action="modal#close">Annuler</button></div>
    </dialog>`);
  const dialog = document.querySelector('dialog');
  dialog.showModal = () => dialog.setAttribute('open', '');
  dialog.close = () => dialog.removeAttribute('open');
  const trigger = document.querySelector('#trigger');
  trigger.focus();
  const application = await start('modal', ModalController);

  window.dispatchEvent(new CustomEvent('modal:open', { detail: { id: 'confirm' } }));
  assert.equal(dialog.hasAttribute('open'), true);
  document.querySelector('[data-action="modal#close"]').click();
  assert.equal(dialog.hasAttribute('open'), false);
  assert.equal(document.activeElement, trigger);

  application.stop();
  dom.window.close();
});

test('modal moves focus into the dialog and keeps keyboard focus contained', async () => {
  const dom = installDom(`
    <button id="trigger">Ouvrir</button>
    <dialog id="confirm" data-controller="modal" data-action="modal:open@window->modal#open">
      <div data-modal-target="panel"><button id="first">Confirmer</button><button id="last">Annuler</button></div>
    </dialog>`);
  const dialog = document.querySelector('dialog');
  dialog.showModal = () => dialog.setAttribute('open', '');
  dialog.close = () => dialog.removeAttribute('open');
  const trigger = document.querySelector('#trigger');
  trigger.focus();
  const application = await start('modal', ModalController);

  window.dispatchEvent(new CustomEvent('modal:open', { detail: { id: 'confirm' } }));
  await new Promise((resolve) => window.setTimeout(resolve, 0));
  assert.equal(document.activeElement.id, 'first');

  document.querySelector('#last').focus();
  document.querySelector('#last').dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }));
  assert.equal(document.activeElement.id, 'first');

  document.querySelector('#first').dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true }));
  assert.equal(document.activeElement.id, 'last');

  application.stop();
  dom.window.close();
});

test('tab bar moves focus into the requested Turbo Frame after it loads', async () => {
  const dom = installDom(`
    <nav data-controller="tab-bar" data-action="turbo:frame-load@document->tab-bar#focusFrame">
      <a href="/securite" data-turbo-frame="project-content" data-action="tab-bar#select">Sécurité</a>
    </nav>
    <turbo-frame id="project-content"><h2 data-tab-focus>Sécurité</h2></turbo-frame>`);
  const application = await start('tab-bar', TabBarController);
  const link = document.querySelector('a');
  const frame = document.querySelector('turbo-frame');

  link.click();
  frame.dispatchEvent(new CustomEvent('turbo:frame-load', { bubbles: true }));
  assert.equal(document.activeElement, document.querySelector('h2'));
  assert.equal(document.activeElement.getAttribute('tabindex'), '-1');

  application.stop();
  dom.window.close();
});

test('network state announces offline and online transitions in French', async () => {
  const dom = installDom('<p data-controller="network-state" data-network-state-target="banner" hidden>Hors ligne</p>');
  const application = await start('network-state', NetworkStateController);
  const banner = document.querySelector('[data-network-state-target="banner"]');

  window.dispatchEvent(new dom.window.Event('offline'));
  assert.equal(banner.hidden, false);
  assert.match(banner.textContent, /Hors ligne/);

  window.dispatchEvent(new dom.window.Event('online'));
  assert.equal(banner.hidden, true);

  application.stop();
  dom.window.close();
});
