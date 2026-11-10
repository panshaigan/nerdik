import { expect, test } from '@playwright/test';
import { login, uniqueLabel, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('player creates a group and sees the shared calendar', async ({ page }) => {
    const fixtures = loadFixtures();
    const groupName = uniqueLabel('E2E Player Group');

    await login(page, fixtures.player.email, fixtures.player.password);
    await page.goto('/groups');
    await waitForLivewireIdle(page);

    await page.locator('[data-ui="groups-create-open"]').click();
    await expect(page.locator('[data-ui="groups-create-modal"]')).toBeVisible();
    await page.locator('[data-ui="groups-create-form"] input[type="text"]').first().fill(groupName);
    await page.locator('[data-ui="groups-create-form"] button[type="submit"]').click();
    await page.waitForURL(/\/groups\//);
    await waitForLivewireIdle(page);

    await expect(page.locator('[data-ui="group-show-tabs"]')).toBeVisible();
    await expect(page.locator('[data-ui="group-show-tab-calendar"]')).toBeVisible();
    await expect(page.locator('[data-ui="group-availability-calendar"]')).toBeVisible();
});
