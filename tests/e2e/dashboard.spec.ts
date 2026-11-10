import { expect, test } from '@playwright/test';
import { login, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('signed-in player sees dashboard stats and groups shortcuts', async ({ page }) => {
    const fixtures = loadFixtures();

    await login(page, fixtures.player.email, fixtures.player.password);
    await page.goto('/dashboard');
    await waitForLivewireIdle(page);

    await expect(page.locator('[data-ui="dashboard-activity-stats"]')).toBeVisible();
    await expect(page.locator('[data-ui="dashboard-quick-actions"]')).toBeVisible();
    await expect(page.locator('[data-ui="dashboard-action-groups"]')).toBeVisible();
});
