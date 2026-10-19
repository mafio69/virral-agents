import { defineConfig } from '@playwright/test';
import { fileURLToPath } from 'node:url';
import { mkdirSync } from 'node:fs';

process.env.PLAYWRIGHT_BROWSERS_PATH = fileURLToPath(new URL('./runtime/browsers', import.meta.url));
process.env.TMPDIR = fileURLToPath(new URL('./runtime/browser-temp', import.meta.url));
mkdirSync(process.env.TMPDIR, { recursive: true });
const root = fileURLToPath(new URL('../../', import.meta.url));

export default defineConfig({
    testDir: '.', testMatch: 'flows.spec.mjs', workers: 1, fullyParallel: false,
    timeout: 45_000, retries: 0,
    globalSetup: './setup.mjs',
    reporter: [['list'], ['html', { outputFolder: 'runtime/report', open: 'never' }]],
    outputDir: 'runtime/results',
    use: { baseURL: 'http://127.0.0.1:18181', browserName: 'chromium',
        screenshot: 'only-on-failure', trace: 'off', video: 'off' },
    webServer: [
        { command: 'php -S 127.0.0.1:18182 tests/e2e/dependencies.php',
            cwd: root, stderr: 'ignore', stdout: 'ignore',
            url: 'http://127.0.0.1:18182/health', reuseExistingServer: false },
        { command: 'php tests/e2e/bootstrap.php && php -S 127.0.0.1:18181 tests/e2e/app.php',
            cwd: root, stderr: 'ignore', stdout: 'ignore',
            url: 'http://127.0.0.1:18181/health', reuseExistingServer: false },
    ],
});
