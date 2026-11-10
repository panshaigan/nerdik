import { expect, test } from '@playwright/test';
import { login, openEventManageMenu, submitScheduleActivityForm, uniqueLabel, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('organizer schedules an activity and slot from the event page', async ({ page }) => {
    const fixtures = loadFixtures();
    const activityName = uniqueLabel('E2E Scheduled Table');

    await login(page, fixtures.organizer.email, fixtures.organizer.password);
    await page.goto(`${fixtures.event.path}?tab=plan`);
    await waitForLivewireIdle(page);

    await openEventManageMenu(page);
    await page.getByRole('menuitem', { name: 'Add scheduled activity' }).click();
    await expect(page.locator('#event-schedule-activity-form')).toBeVisible({ timeout: 20_000 });

    await submitScheduleActivityForm(page, {
        name: activityName,
        startsAt: fixtures.schedule_activity.starts_at,
        endsAt: fixtures.schedule_activity.ends_at,
    });

    await page.goto(`${fixtures.event.path}?tab=plan`);
    await waitForLivewireIdle(page);
    await expect(page.locator('[data-ui="event-show-slots"]')).toContainText(activityName, { timeout: 30_000 });
});
