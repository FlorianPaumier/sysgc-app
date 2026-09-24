import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const read = (path) => readFile(new URL(path, import.meta.url), 'utf8');

test('Symfony trusts only the immediate Laravel Cloud or local reverse proxy and forwarded headers', async () => {
  const framework = await read('../config/packages/framework.yaml');

  assert.match(framework, /trusted_proxies:\s*'127\.0\.0\.1,REMOTE_ADDR'/);
  assert.match(framework, /trusted_headers:\s*\[[^\]]*x-forwarded-for[^\]]*x-forwarded-host[^\]]*x-forwarded-proto[^\]]*x-forwarded-port[^\]]*x-forwarded-prefix[^\]]*\]/s);
});

test('the web server emits the required browser security policy', async () => {
  const caddy = await read('../frankenphp/Caddyfile');

  assert.match(caddy, /Strict-Transport-Security\s+"max-age=31536000; includeSubDomains"/);
  assert.match(caddy, /X-Content-Type-Options\s+"nosniff"/);
  assert.match(caddy, /Referrer-Policy\s+"strict-origin-when-cross-origin"/);
  assert.match(caddy, /Permissions-Policy\s+"[^\"]*camera=\(self\)[^\"]*geolocation=\(\)[^\"]*microphone=\(\)[^\"]*"/);
  assert.match(caddy, /Content-Security-Policy\s+"[^\"]*default-src 'self'[^\"]*frame-ancestors 'none'[^\"]*"/);
  assert.match(caddy, /style-src 'self' 'unsafe-inline' https:\/\/fonts\.googleapis\.com/);
  assert.match(caddy, /font-src 'self' https:\/\/fonts\.gstatic\.com/);
  assert.match(caddy, /img-src 'self' data: blob:/);
});
