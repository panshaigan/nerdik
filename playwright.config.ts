import { defineConfig, devices } from '@playwright/test';

const isCi = Boolean(process.env.CI);
const baseURL = process.env.PLAYWRIGHT_BASE_URL
    ?? (isCi ? 'http://localhost:8000' : 'http://localhost');

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: isCi,
    retries: isCi ? 1 : 0,
    workers: 1,
    reporter: isCi ? [['github'], ['html', { open: 'never' }]] : 'list',
    timeout: 60_000,
    expect: {
        timeout: 15_000,
    },
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        locale: 'en-GB',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: isCi
        ? {
            command: 'php artisan serve --host=127.0.0.1 --port=8000',
            url: 'http://localhost:8000',
            reuseExistingServer: false,
            timeout: 120_000,
        }
        : undefined,
});
