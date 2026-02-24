import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './e2e',
    timeout: 30_000,
    retries: 0,
    workers: 1,
    fullyParallel: false,
    use: {
        baseURL: process.env.BASE_URL || 'http://localhost:8080',
        headless: true,
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'chromium', use: { browserName: 'chromium' } },
    ],
    globalSetup: './e2e/global-setup.ts',
});
