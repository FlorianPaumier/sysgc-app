import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { Application, Controller } from '@hotwired/stimulus';
import SafetyMediaController from '../assets/controllers/safety_media_controller.js';

test('forwards photo and signature browser events to the owning Live component', async () => {
  const dom = new JSDOM('<div data-controller="live safety-media"><section data-safety-media-target="source"></section></div>', { url: 'https://sysgc.test' });
  globalThis.window = dom.window;
  globalThis.document = dom.window.document;
  globalThis.Element = dom.window.Element;
  globalThis.HTMLElement = dom.window.HTMLElement;
  globalThis.Node = dom.window.Node;
  globalThis.MutationObserver = dom.window.MutationObserver;
  Object.defineProperty(window.navigator, 'onLine', { value: true, configurable: true });

  const application = Application.start();
  const actions = [];
  application.register('live', class extends Controller { connect() { this.component = { action: (...args) => actions.push(args) }; } });
  application.register('safety-media', SafetyMediaController);
  await new Promise((resolve) => window.setTimeout(resolve, 0));
  const source = document.querySelector('[data-safety-media-target="source"]');
  source.dispatchEvent(new window.CustomEvent('photo:uploaded', { bubbles: true, detail: { dataUrl: 'data:image/jpeg;base64,photo' } }));
  source.dispatchEvent(new window.CustomEvent('signature:added', { bubbles: true, detail: { workerName: 'Camille', dataUrl: 'data:image/png;base64,signature' } }));

  assert.deepEqual(actions, [['addPhoto', { path: 'data:image/jpeg;base64,photo' }, 0], ['addSignature', { workerName: 'Camille', dataUrl: 'data:image/png;base64,signature' }, 0]]);
  application.stop();
  dom.window.close();
});
