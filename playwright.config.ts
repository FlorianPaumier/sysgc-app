import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E config — runs against the local Docker app (https://localhost).
 * The @playwright/test package is installed in a later card; this file establishes
 * the layout. Run with: npx playwright test (after `npx playwright install`).
 *
 * See specs/09-tests.md (Playwright replaces Panther) and CLAUDE.md.
 */
export default defineConfig({
    testDir: './e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: 'html',
    use: {
        baseURL: 'https://localhost',
        ignoreHTTPSErrors: true, // accept the self-signed Caddy cert
        trace: 'on-first-retry',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
});
