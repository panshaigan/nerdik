import { expect, test } from '@playwright/test';
import { login, uniqueLabel, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('organizer edits a slot from the event plan', async ({ page }) => {
    const fixtures = loadFixtures();
    const newName = uniqueLabel('E2E Edited Slot');

    await login(page, fixtures.organizer.email, fixtures.organizer.password);
    await page.goto(`${fixtures.event.path}?tab=plan`);
    await waitForLivewireIdle(page);

    await page.evaluate((slotId) => {
        window.openSlotEditModal?.(slotId);
    }, fixtures.empty_slot.id);

    const modalBody = page.locator('#slot-edit-modal-body');
    await expect(modalBody.locator('[data-slot-name-input]')).toBeVisible({ timeout: 20_000 });

    await modalBody.locator('[data-slot-name-input]').fill(newName);

    await modalBody.getByRole('button', { name: 'Save' }).click();
    await waitForLivewireIdle(page);

    await expect(page.locator('[data-ui="event-show-slots"]')).toContainText(newName, { timeout: 20_000 });
});
