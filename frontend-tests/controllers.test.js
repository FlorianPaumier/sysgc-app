import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { Application } from '@hotwired/stimulus';
import NavController from '../assets/controllers/nav_controller.js';
import SidebarController from '../assets/controllers/sidebar_controller.js';

function installDom(html) {
  const dom = new JSDOM(html, { url: 'https://sysgc.test' });
  globalThis.window = dom.window;
  globalThis.document = dom.window.document;
  globalThis.Element = dom.window.Element;
  globalThis.HTMLElement = dom.window.HTMLElement;
  globalThis.KeyboardEvent = dom.window.KeyboardEvent;
  globalThis.MouseEvent = dom.window.MouseEvent;
  globalThis.MutationObserver = dom.window.MutationObserver;
  globalThis.localStorage = dom.window.localStorage;
  return dom;
}

async function start(identifier, Controller) {
  const application = Application.start();
  application.register(identifier, Controller);
  await new Promise((resolve) => window.setTimeout(resolve, 0));
  return application;
}

test('public navigation opens, synchronizes aria, and closes from its actions', async () => {
  const dom = installDom(`
    <nav data-controller="nav" data-nav-target="menu">
      <button data-nav-target="toggle" data-action="nav#toggle" aria-expanded="false">Menu</button>
      <button data-action="nav#close">Close</button>
    </nav>`);
  const application = await start('nav', NavController);
  const menu = document.querySelector('nav');
  const [toggle, close] = document.querySelectorAll('button');

  toggle.click();
  assert.equal(menu.classList.contains('nav--open'), true);
  assert.equal(toggle.getAttribute('aria-expanded'), 'true');
  assert.equal(document.body.style.overflow, 'hidden');

  toggle.click();
  assert.equal(menu.classList.contains('nav--open'), false);
  assert.equal(toggle.getAttribute('aria-expanded'), 'false');
  assert.equal(document.body.style.overflow, '');

  toggle.click();
  close.click();
  assert.equal(menu.classList.contains('nav--open'), false);

  application.stop();
  dom.window.close();
});

test('public navigation closes on Escape but ignores other keys', async () => {
  const dom = installDom(`<nav data-controller="nav" data-nav-target="menu"><button data-nav-target="toggle" data-action="nav#toggle">Menu</button></nav>`);
  const application = await start('nav', NavController);
  const menu = document.querySelector('nav');
  document.querySelector('button').click();

  document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
  assert.equal(menu.classList.contains('nav--open'), true);
  document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
  assert.equal(menu.classList.contains('nav--open'), false);

  application.stop();
  dom.window.close();
});

test('app sidebar restores and persists collapsed state using the user key', async () => {
  const dom = installDom(`
    <div data-controller="sidebar" data-sidebar-storage-key="sysgc:user-1">
      <aside data-sidebar-target="sidebar"></aside>
      <button data-sidebar-target="toggle" data-action="sidebar#toggle"></button>
    </div>`);
  localStorage.setItem('sysgc:user-1', '1');
  const application = await start('sidebar', SidebarController);
  const sidebar = document.querySelector('aside');
  const toggle = document.querySelector('button');

  assert.equal(sidebar.classList.contains('app-sidebar--collapsed'), true);
  assert.equal(document.querySelector('[data-controller="sidebar"]').classList.contains('app-shell--sidebar-collapsed'), true);
  assert.equal(document.querySelector('[data-controller="sidebar"]').dataset.sidebarCollapsed, 'true');
  assert.equal(toggle.getAttribute('aria-expanded'), 'false');
  assert.equal(toggle.getAttribute('aria-label'), 'Agrandir la barre latérale');

  toggle.click();
  assert.equal(sidebar.classList.contains('app-sidebar--collapsed'), false);
  assert.equal(document.querySelector('[data-controller="sidebar"]').classList.contains('app-shell--sidebar-collapsed'), false);
  assert.equal(document.querySelector('[data-controller="sidebar"]').dataset.sidebarCollapsed, 'false');
  assert.equal(toggle.getAttribute('aria-expanded'), 'true');
  assert.equal(toggle.getAttribute('aria-label'), 'Réduire la barre latérale');
  assert.equal(localStorage.getItem('sysgc:user-1'), '0');

  application.stop();
  dom.window.close();
});

test('app sidebar uses a safe default persistence key', async () => {
  const dom = installDom(`<div data-controller="sidebar"><aside data-sidebar-target="sidebar"></aside><button data-sidebar-target="toggle"></button></div>`);
  const application = await start('sidebar', SidebarController);

  assert.equal(document.querySelector('aside').classList.contains('app-sidebar--collapsed'), false);
  assert.equal(localStorage.getItem('sysgc:sidebar'), '0');

  application.stop();
  dom.window.close();
});
