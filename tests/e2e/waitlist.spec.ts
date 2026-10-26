import { expect, test } from '@playwright/test';
import { login, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('player joins and leaves the activity waitlist', async ({ page }) => {
    const fixtures = loadFixtures();

    await login(page, fixtures.player.email, fixtures.player.password);
    await page.goto(`${fixtures.waitlist_activity.path}?tab=participation`);
    await waitForLivewireIdle(page);

    const actions = page.locator('[data-ui="activity-show-waitlist-actions"]');
    await actions.getByRole('button', { name: 'Join', exact: true }).click();
    await waitForLivewireIdle(page);
    await expect(actions.getByRole('button', { name: 'Leave', exact: true })).toBeVisible();

    await actions.getByRole('button', { name: 'Leave', exact: true }).click();
    await waitForLivewireIdle(page);
    await expect(actions.getByRole('button', { name: 'Join', exact: true })).toBeVisible();
});
