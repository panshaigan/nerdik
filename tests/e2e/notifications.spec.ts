import { expect, test } from '@playwright/test';
import { login, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('organizer opens a notification and clears the unread badge', async ({ page }) => {
    const fixtures = loadFixtures();

    await login(page, fixtures.organizer.email, fixtures.organizer.password);
    await waitForLivewireIdle(page);

    await expect(page.locator('[data-ui="nav-notifications-badge"]')).toBeVisible();

    await page.locator('[data-ui="nav-notifications-trigger"]').click();
    await expect(page.locator('[data-ui="nav-notifications-panel"]')).toBeVisible();
    await page
        .locator('[data-ui="nav-notification-item"]')
        .filter({ hasText: fixtures.event.name })
        .click();

    await page.waitForURL((url) => url.pathname.includes(`/events/${fixtures.event.slug}`), { timeout: 20_000 });
    expect(page.url()).toContain('tab=proposals');
    await waitForLivewireIdle(page);

    await page.goto('/dashboard');
    await page.reload();
    await waitForLivewireIdle(page);

    await expect(page.locator('[data-ui="nav-notifications-badge"]')).toBeHidden();
});
