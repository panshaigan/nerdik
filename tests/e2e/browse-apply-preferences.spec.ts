import { expect, test } from '@playwright/test';
import { login, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('player applies play preferences from browse filters', async ({ page }) => {
    const fixtures = loadFixtures();

    await login(page, fixtures.player.email, fixtures.player.password);
    await page.goto('/search?q=noisy-filter');
    await waitForLivewireIdle(page);

    await page.locator('[data-ui="browse-filters-toggle"]').click();
    await expect(page.locator('[data-ui="browse-events-filters-panel"]')).toBeVisible();

    await page.locator('[data-ui="browse-events-apply-preferences"]').click();
    await waitForLivewireIdle(page);

    await expect(page).toHaveURL(/map_view=(?:1|true)/);
    await expect(page).toHaveURL(/min_lat=/);
    await expect(page).toHaveURL(new RegExp(`tag_ids.*${fixtures.play_preferences.tag_id}`));
});
