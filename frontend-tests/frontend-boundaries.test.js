import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const read = (path) => readFile(new URL(path, import.meta.url), 'utf8');

test('public entrypoint only registers public navigation behavior', async () => {
  const source = await read('../assets/public.js');

  assert.match(source, /NavController/);
  assert.doesNotMatch(source, /SidebarController|sidebar_controller/);
});

test('authenticated entrypoint only registers application-shell behavior', async () => {
  const source = await read('../assets/authenticated.js');

  assert.match(source, /SidebarController/);
  assert.match(source, /startStimulusApp/);
  assert.doesNotMatch(source, /NavController|nav_controller/);
});

test('authenticated entrypoint registers the safety-only progressive enhancement controllers', async () => {
  const source = await read('../assets/authenticated.js');

  assert.match(source, /OfflineQueueController/);
  assert.match(source, /PhotoUploadController/);
  assert.match(source, /SignatureCanvasController/);
  assert.match(source, /CameraCaptureController/);
});

test('public and authenticated stylesheets do not import one another', async () => {
  const [publicCss, authenticatedCss] = await Promise.all([
    read('../assets/styles/public.css'),
    read('../assets/styles/authenticated.css'),
  ]);

  assert.doesNotMatch(publicCss, /authenticated\.css/);
  assert.doesNotMatch(authenticatedCss, /public\.css/);
});

test('Live Component styles load without a CSP-blocked data script module', async () => {
  const config = JSON.parse(await read('../assets/controllers.json'));
  assert.equal(config.controllers['@symfony/ux-live-component'].live.autoimport['@symfony/ux-live-component/dist/live.min.css'], false);
  assert.match(await read('../templates/app_base.html.twig'), /asset\('@symfony\/ux-live-component\/live\.min\.css'\)/);
});
