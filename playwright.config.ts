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
    globalSetup: './e2e/global-setup.ts',
    // Registration tests share the local IP-based limiter and Mailpit inbox.
    // Run serially so authentication flows remain deterministic.
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: 'html',
    use: {
        // The local HTTPS listener can run on a non-default port (for example
        // 18443 in the desktop test environment). Keep the default for Docker,
        // while allowing CI/desktop runs to select the live endpoint without
        // rewriting every relative navigation in the suite.
        baseURL: process.env.SYSGC_BASE_URL ?? 'https://localhost',
        ignoreHTTPSErrors: true, // accept the self-signed Caddy cert
        trace: 'on-first-retry',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
});
