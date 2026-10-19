import { defineConfig } from '@playwright/test';

// Read-only unauthenticated smoke check of the owner's running local installation.
export default defineConfig({
    testDir: '.', testMatch: 'local-deployment.spec.mjs', workers: 1, retries: 0,
    timeout: 15_000, reporter: 'list', outputDir: 'runtime/deployment-results',
    use: { baseURL: 'http://127.0.0.1:8181' },
});
