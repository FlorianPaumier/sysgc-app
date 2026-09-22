import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { Application } from '@hotwired/stimulus';
import OfflineQueueController from '../assets/controllers/offline_queue_controller.js';

test('queues the current safety report and prevents the Live request while offline', async () => {
  const dom = new JSDOM(`
    <section data-controller="offline-queue" data-offline-queue-key-value="safety:project:2026-06-09" data-action="safety:saved->offline-queue#saved">
      <p hidden data-offline-queue-target="banner"></p>
      <select data-model="weather"><option value="Pluie" selected>Pluie</option></select>
      <textarea data-model="incidentReport">Zone glissante</textarea>
      <button data-action="click->offline-queue#queueSafetyReport live#action">Enregistrer</button>
    </section>`, { url: 'https://sysgc.test' });
  globalThis.window = dom.window;
  globalThis.document = dom.window.document;
  globalThis.Element = dom.window.Element;
  globalThis.HTMLElement = dom.window.HTMLElement;
  globalThis.Node = dom.window.Node;
  globalThis.KeyboardEvent = dom.window.KeyboardEvent;
  globalThis.MouseEvent = dom.window.MouseEvent;
  globalThis.MutationObserver = dom.window.MutationObserver;
  globalThis.localStorage = dom.window.localStorage;
  Object.defineProperty(window.navigator, 'onLine', { value: false, configurable: true });

  const application = Application.start();
  application.register('offline-queue', OfflineQueueController);
  await new Promise((resolve) => window.setTimeout(resolve, 0));
  document.querySelector('button').click();

  const queued = JSON.parse(localStorage.getItem('safety:project:2026-06-09'));
  assert.equal(queued.length, 1);
  assert.deepEqual(queued[0].payload, { weather: 'Pluie', incidentReport: 'Zone glissante' });
  assert.equal(document.querySelector('[data-offline-queue-target="banner"]').hidden, false);

  application.stop();
  dom.window.close();
});

test('queues a stable event envelope once and exposes queued status', async () => {
  const dom = new JSDOM(`
    <section data-controller="offline-queue" data-offline-queue-key-value="safety:project:2026-06-09">
      <p hidden data-offline-queue-target="banner"></p>
      <button data-action="click->offline-queue#queueSafetyReport">Enregistrer</button>
    </section>`, { url: 'https://sysgc.test' });
  globalThis.window = dom.window;
  globalThis.document = dom.window.document;
  globalThis.Element = dom.window.Element;
  globalThis.HTMLElement = dom.window.HTMLElement;
  globalThis.Node = dom.window.Node;
  globalThis.MutationObserver = dom.window.MutationObserver;
  globalThis.localStorage = dom.window.localStorage;
  Object.defineProperty(window.navigator, 'onLine', { value: false, configurable: true });

  const application = Application.start();
  application.register('offline-queue', OfflineQueueController);
  await new Promise((resolve) => window.setTimeout(resolve, 0));
  const button = document.querySelector('button');
  button.click();
  button.click();

  const queue = JSON.parse(localStorage.getItem('safety:project:2026-06-09'));
  assert.equal(queue.length, 1);
  assert.match(queue[0].clientEventId, /^[0-9a-f-]{36}$/);
  assert.equal(queue[0].status, 'queued');
  assert.equal(document.querySelector('[data-offline-queue-target="banner"]').textContent, 'Hors ligne — 1 modification en attente.');

  application.stop();
  dom.window.close();
});
