import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { randomUUID } from 'node:crypto';

test.describe.configure({ mode: 'serial' });

const excludedPath = /^(?:\/logout|\/api|\/dashboard\/alerts|\/organisation\/exporter|\/.*(?:\.pdf|\.csv))$/;

async function audit(page: import('@playwright/test').Page, url: string): Promise<void> {
  await page.goto(url);
  const results = await new AxeBuilder({ page })
    .exclude('.sf-toolbar')
    .analyze();

  expect(results.violations, `${url}\n${JSON.stringify(results.violations, null, 2)}`).toEqual([]);
}

async function collectInternalPages(page: import('@playwright/test').Page): Promise<string[]> {
  const origin = new URL(page.url()).origin;
  const hrefs = await page.locator('a[href]').evaluateAll((links) => links
    .map((link) => (link as HTMLAnchorElement).href)
    .filter(Boolean));

  return [...new Set(hrefs)]
    .filter((href) => {
      const url = new URL(href);
      return url.origin === origin
        && !url.hash
        && !excludedPath.test(url.pathname)
        && !url.pathname.startsWith('/.well-known')
        && !url.pathname.startsWith('/_');
    })
    .sort();
}

async function registerOwner(page: import('@playwright/test').Page): Promise<void> {
  const email = `axe-${randomUUID()}@example.com`;
  const mailpitUrl = process.env.SYSGC_MAILPIT_URL ?? 'http://127.0.0.1:8025';
  await page.goto('/inscription');
  await page.fill('input[type="email"]', email);
  await page.fill('input[name*="organization"]', 'Axe Accessibility Test');
  await page.fill('input[name*="rbq"]', 'RBQ-1234-5678-90');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/login/check-email');

  const deadline = Date.now() + 15_000;
  let verificationUrl = '';
  while (Date.now() < deadline && verificationUrl === '') {
    const inbox = await (await fetch(`${mailpitUrl}/api/v1/messages`)).json() as { messages: Array<{ ID: string; To: Array<{ Address: string }> }> };
    const message = inbox.messages.find(item => item.To.some(recipient => recipient.Address === email));
    if (message) {
      const detail = await (await fetch(`${mailpitUrl}/api/v1/message/${message.ID}`)).json() as { Text: string };
      verificationUrl = detail.Text.match(/https?:\/\/\S+\/connexion\/verifier\?\S+/)?.[0].replace('&amp;', '&') ?? '';
    }
    if (!verificationUrl) await new Promise(resolve => setTimeout(resolve, 250));
  }

  expect(verificationUrl).not.toBe('');
  await page.goto(verificationUrl);
  await page.waitForURL('**/dashboard');
}

test('all public pages reachable from the public navigation pass axe', async ({ page }) => {
  const pages = new Set<string>();
  for (const start of ['/', '/connexion', '/inscription', '/contact']) {
    await page.goto(start);
    for (const url of await collectInternalPages(page)) pages.add(url);
  }

  for (const url of pages) await audit(page, url);
});

test('all authenticated pages reachable from the application shell pass axe', async ({ page }) => {
  await registerOwner(page);

  const pages = new Set<string>();
  const queue = ['/dashboard'];
  while (queue.length > 0 && pages.size < 100) {
    const url = queue.shift()!;
    if (pages.has(url)) continue;
    pages.add(url);
    await audit(page, url);
    for (const linked of await collectInternalPages(page)) {
      const pathname = new URL(linked).pathname;
      if (pathname.startsWith('/dashboard') || pathname.startsWith('/chantiers') || pathname.startsWith('/organisation') || pathname.startsWith('/securite') || pathname.startsWith('/declarations') || pathname.startsWith('/rbq') || pathname.startsWith('/avenants') || pathname.startsWith('/factures') || pathname.startsWith('/travailleurs') || pathname.startsWith('/facturation')) {
        queue.push(linked);
      }
    }
  }

  expect(pages.size).toBeGreaterThan(1);
});
