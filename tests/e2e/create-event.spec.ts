import { expect, test } from '@playwright/test';
import { futureDatetimeRange, login, searchFor, setDatetime, uniqueLabel, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('organizer creates an event through the tabbed form', async ({ page }) => {
    const fixtures = loadFixtures();
    const name = uniqueLabel('E2E Created Event');
    const { start, end } = futureDatetimeRange();

    await login(page, fixtures.organizer.email, fixtures.organizer.password);
    await page.goto('/events/create');
    await waitForLivewireIdle(page);

    await page.locator('[data-event-name-input]').fill(name);
    await setDatetime(page, page.locator('[data-event-start-at]'), start);
    await setDatetime(page, page.locator('[data-event-ends-at]'), end);

    await page.locator('[data-ui="event-submit"]').click();
    await page.waitForURL(/\/search/);
    await waitForLivewireIdle(page);

    await searchFor(page, name);
    await expect(page.getByRole('link', { name, exact: true })).toBeVisible({ timeout: 30_000 });
});
