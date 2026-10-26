import { expect, test } from '@playwright/test';
import { futureDatetimeRange, login, searchFor, selectSavedPlace, setDatetime, uniqueLabel, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('organizer creates a self-hosted activity and finds it in search', async ({ page }) => {
    const fixtures = loadFixtures();
    const name = uniqueLabel('E2E Created Activity');
    const { start } = futureDatetimeRange();

    await login(page, fixtures.organizer.email, fixtures.organizer.password);
    await page.goto('/activities/create');
    await waitForLivewireIdle(page);

    await page.locator('[data-activity-name-input]').fill(name);

    await page.getByRole('tab', { name: 'Location & time' }).click();
    await page.getByRole('radio', { name: "I'll run it myself" }).click();
    await waitForLivewireIdle(page);

    await selectSavedPlace(page, fixtures.place.name);
    await setDatetime(page, page.locator('[data-selfhost-start-input]'), start);

    await page.locator('[data-ui="activity-submit"]').click();
    await page.waitForURL(/\/search/);
    await waitForLivewireIdle(page);

    await searchFor(page, name);
    await expect(page.locator('[data-ui="activity-card"]').filter({ hasText: name })).toBeVisible();
});
