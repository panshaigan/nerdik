import { expect, test } from '@playwright/test';
import { login, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('player joins an activity from the event plan preview', async ({ page }) => {
    const fixtures = loadFixtures();

    await login(page, fixtures.player.email, fixtures.player.password);
    await page.goto(`${fixtures.event.path}?tab=plan`);
    await waitForLivewireIdle(page);

    await page
        .locator('[data-ui="event-show-slots"]')
        .getByRole('button', { name: fixtures.activity.name, exact: true })
        .click();
    await expect(page.locator('[data-ui="event-activity-preview-modal"]')).toBeVisible();

    const actions = page.locator('[data-ui="event-activity-preview-actions"]');
    const joinButton = actions.getByRole('button', { name: 'Join' });
    if (await joinButton.isVisible()) {
        await joinButton.click();
    } else {
        await actions.getByRole('button', { name: 'Leave' }).click();
        await waitForLivewireIdle(page);
        await joinButton.click();
    }
    await waitForLivewireIdle(page);

    await expect(actions.getByRole('button', { name: 'Leave' })).toBeVisible();
});
