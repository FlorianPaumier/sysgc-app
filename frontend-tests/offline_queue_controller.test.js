import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { Application, Controller } from '@hotwired/stimulus';
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


test('offline reports preserve live photos, signatures and checklist edits through replay', async () => {
  const dom = new JSDOM('<section data-controller="offline-queue" data-offline-queue-key-value="safety:test"><p data-offline-queue-target="banner"></p><div data-controller="live"></div></section>', { url: 'https://sysgc.test' });
  for (const name of ['window', 'document', 'Element', 'HTMLElement', 'Node', 'MutationObserver', 'localStorage']) globalThis[name] = name === 'window' ? dom.window : dom.window[name];
  const state = { weather: 'Pluie', incidentReport: '', checklistItems: [{ id: 'helmet', checked: true }], photos: ['photo'], signatures: [{ workerName: 'A', dataUrl: 'signature' }], version: 2 };
  const replayed = {};
  const application = Application.start();
  application.register('live', class extends Controller { connect() { this.component = { getData: key => state[key], set: (key, value) => { replayed[key] = value; }, action: async () => {} }; } });
  application.register('offline-queue', OfflineQueueController);
  await new Promise(resolve => window.setTimeout(resolve, 0));
  const queue = application.getControllerForElementAndIdentifier(document.querySelector('section'), 'offline-queue');
  queue.queue(queue.report());
  const stored = JSON.parse(localStorage.getItem('safety:test'))[0];
  assert.deepEqual(stored.payload.photos, state.photos);
  assert.deepEqual(stored.payload.signatures, state.signatures);
  await queue.sync();
  assert.deepEqual(replayed.photos, state.photos);
  assert.deepEqual(replayed.signatures, state.signatures);
  application.stop();
  dom.window.close();
});
