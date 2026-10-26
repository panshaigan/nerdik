import { expect, test } from '@playwright/test';
import { login, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('player joins an activity from the event plan preview', async ({ page }) => {
    const fixtures = loadFixtures();

    await login(page, fixtures.player.email, fixtures.player.password);
    await page.goto(fixtures.event.path);
    await waitForLivewireIdle(page);

    await page.locator('[data-ui="event-show-slot-open-activity-preview"]').click();
    await expect(page.locator('[data-ui="event-activity-preview-modal"]')).toBeVisible();

    await page.locator('[data-ui="event-activity-preview-actions"]').getByRole('button', { name: 'Join' }).click();
    await waitForLivewireIdle(page);

    await expect(page.locator('[data-ui="event-activity-preview-actions"]').getByRole('button', { name: 'Leave' })).toBeVisible();
});
