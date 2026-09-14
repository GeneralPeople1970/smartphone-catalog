import { mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { defineConfig } from '@playwright/test';

const runtime = process.env.BROWSER_TEST_RUNTIME || mkdtempSync(join(tmpdir(), 'catalog-browser-'));
const port = process.env.BROWSER_TEST_PORT || '8765';
process.env.BROWSER_TEST_RUNTIME = runtime;
process.env.BROWSER_TEST_PORT = port;

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    workers: 1,
    timeout: 30_000,
    expect: { timeout: 8_000 },
    reporter: 'list',
    outputDir: join(runtime, 'results'),
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        viewport: { width: 1440, height: 900 },
        launchOptions: { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE },
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    webServer: {
        command: 'node scripts/serve-browser-tests.mjs',
        url: `http://127.0.0.1:${port}/up`,
        reuseExistingServer: false,
        timeout: 60_000,
        env: { BROWSER_TEST_RUNTIME: runtime, BROWSER_TEST_PORT: port },
    },
});
