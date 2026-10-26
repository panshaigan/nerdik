import { expect, test } from '@playwright/test';
import { waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('map browse opens an event from a marker popup', async ({ page }) => {
    const fixtures = loadFixtures();

    await page.goto(fixtures.map.path);
    await waitForLivewireIdle(page);

    const map = page.locator('[data-ui="browse-events-map"]');
    await expect(map).toBeVisible();
    await expect(map).toHaveClass(/leaflet-container/);

    const pin = map.locator('.browse-map-listing-pin-root').first();
    await expect(pin).toBeVisible();
    await pin.click();

    const popup = page.locator('.browse-map-listing-popup').filter({ hasText: fixtures.event.name });
    await expect(popup).toBeVisible();
    await popup.locator('a.browse-map-popup-details').click();

    await page.waitForURL(new RegExp(`${fixtures.event.path.replace('/', '\\/')}`));
    await waitForLivewireIdle(page);
    await expect(page.getByRole('heading', { name: fixtures.event.name })).toBeVisible();
});
