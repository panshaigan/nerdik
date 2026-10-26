import { expect, test } from '@playwright/test';
import { saveCurrentSearch, searchFor, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('saved search is restored when returning to /search', async ({ page }) => {
    const fixtures = loadFixtures();

    await page.goto('/search');
    await searchFor(page, fixtures.event.name);
    await expect(page.locator('[data-ui="event-card"]').filter({ hasText: fixtures.event.name })).toBeVisible();

    await saveCurrentSearch(page);

    await page.goto('/privacy');
    await page.goto('/search');
    await waitForLivewireIdle(page);

    await expect(page).toHaveURL(/[?&]q=/);
    await expect(page.locator('[data-ui="event-card"]').filter({ hasText: fixtures.event.name })).toBeVisible();
});
