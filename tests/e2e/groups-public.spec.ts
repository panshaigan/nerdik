import { expect, test } from '@playwright/test';
import { login, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('player joins a public group and unlocks the calendar', async ({ page }) => {
    const fixtures = loadFixtures();

    await login(page, fixtures.player.email, fixtures.player.password);
    await page.goto(fixtures.public_group.path);
    await waitForLivewireIdle(page);

    await expect(page.locator('[data-ui="group-public-card"]')).toBeVisible();
    await expect(page.locator('[data-ui="group-join"]')).toBeVisible();
    await expect(page.locator('[data-ui="group-show-tabs"]')).toHaveCount(0);

    await page.locator('[data-ui="group-join"]').click();
    await waitForLivewireIdle(page);

    await expect(page.locator('[data-ui="group-show-tabs"]')).toBeVisible();
    await expect(page.locator('[data-ui="group-show-tab-calendar"]')).toBeVisible();
    await expect(page.locator('[data-ui="group-availability-calendar"]')).toBeVisible();
});
