import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const read = (path) => readFile(new URL(path, import.meta.url), 'utf8');

test('authenticated screens use the paper vocabulary and semantic styling hooks', async () => {
  const [home, upgrade, settings, authenticatedCss] = await Promise.all([
    read('../templates/home/index.html.twig'),
    read('../templates/billing/upgrade.html.twig'),
    read('../templates/organization/settings.html.twig'),
    read('../assets/styles/authenticated.css'),
  ]);

  assert.match(home, /Classés par chantier/);
  assert.doesNotMatch(home, /Classés par projet/);
  assert.doesNotMatch(`${upgrade}${settings}`, /(?:rounded-|bg-(?:red|blue|green|zinc)|text-(?:red|blue|green|zinc)|border-zinc|ring-orange)-/);
  assert.doesNotMatch(authenticatedCss, /#[0-9A-Fa-f]{3,8}|border-radius:\s*\.?\d/);
});
