import { expect, test } from '@playwright/test';
import { cropAndApply, e2eFixturePath, login, setEventLogoSourceUpload, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('organizer uploads and crops an event cover image', async ({ page }) => {
    const fixtures = loadFixtures();
    const coverPath = e2eFixturePath('cover.png');

    await login(page, fixtures.organizer.email, fixtures.organizer.password);
    await page.goto(fixtures.event.edit_path);
    await waitForLivewireIdle(page);

    await page.getByRole('tab', { name: 'Image' }).click();
    await waitForLivewireIdle(page);
    await setEventLogoSourceUpload(page);
    await expect(page.locator('[data-image-crop-dropzone]')).toBeVisible({ timeout: 30_000 });

    await cropAndApply(page, coverPath);

    await page.locator('[data-ui="event-submit"]').click();
    await page.waitForURL(new RegExp(`/events/${fixtures.event.slug}(?!/edit)`));
    await waitForLivewireIdle(page);

    await expect(page.locator('[data-ui="event-show-page-background"]')).toBeVisible();
});
