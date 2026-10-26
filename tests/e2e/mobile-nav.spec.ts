import { devices, expect, test } from '@playwright/test';
import { login, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test.use({ ...devices['Pixel 5'] });

test('mobile drawer navigates to search', async ({ page }) => {
    const { player } = loadFixtures();

    await login(page, player.email, player.password);
    await waitForLivewireIdle(page);

    await page.getByRole('button', { name: 'Open menu' }).click();
    await expect(page.locator('#mobile-nav-drawer')).toBeVisible();

    await page.locator('#mobile-nav-drawer').getByRole('link', { name: 'Search' }).click();
    await page.waitForURL(/\/search/);
    await waitForLivewireIdle(page);

    await expect(page.locator('#mobile-nav-drawer')).toBeHidden();
});
