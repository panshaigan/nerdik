import { expect, test } from '@playwright/test';
import { login, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('guest login returns to the activity that sent them', async ({ page }) => {
    const fixtures = loadFixtures();

    await page.goto(fixtures.activity.path);
    await page.getByRole('tab', { name: 'Sign-ups' }).click();
    await waitForLivewireIdle(page);
    await page.locator('[data-ui="activity-show-guest-join"]').click();

    await expect(page).toHaveURL(/\/login\?/);
    await expect(page).toHaveURL(/return=/);

    await page.locator('[data-ui="auth-login-email"]').fill(fixtures.player.email);
    await page.locator('[data-ui="auth-login-password"]').fill(fixtures.player.password);
    await page.locator('[data-ui="auth-login-submit"]').click();

    await page.waitForURL(new RegExp(`${fixtures.activity.path.replace('/', '\\/')}.*tab=participation`));
    await waitForLivewireIdle(page);
    await expect(page.getByRole('heading', { name: fixtures.activity.name })).toBeVisible();
});

test('signed-in player can open the dashboard', async ({ page }) => {
    const fixtures = loadFixtures();
    await login(page, fixtures.player.email, fixtures.player.password);
    await expect(page).toHaveURL(/\/dashboard/);
});
