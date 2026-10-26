import { execSync } from 'node:child_process';

import { expect, test } from '@playwright/test';
import { clickDatabaseNotification, login, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test.beforeAll(() => {
    execSync('php artisan e2e:prepare', { stdio: 'pipe' });
});

test('organizer opens a notification and clears the unread badge', async ({ page }) => {
    const fixtures = loadFixtures();

    await login(page, fixtures.organizer.email, fixtures.organizer.password);
    await waitForLivewireIdle(page);

    await expect(page.locator('[data-ui="nav-notifications-badge"]')).toBeVisible();

    await page.locator('[data-ui="nav-notifications-trigger"]').click();
    await expect(page.locator('[data-ui="nav-notifications-panel"]')).toBeVisible();
    await clickDatabaseNotification(page, fixtures.notification.id);

    await page.waitForURL((url) => url.pathname.includes(`/events/${fixtures.event.slug}`), { timeout: 20_000 });
    expect(page.url()).toContain('tab=proposals');
    await waitForLivewireIdle(page);

    await expect.poll(async () => {
        await page.goto('/dashboard');
        await waitForLivewireIdle(page);

        return await page.locator('[data-ui="nav-notifications-badge"]').count();
    }).toBe(0);
});
