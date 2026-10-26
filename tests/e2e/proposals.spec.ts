import { expect, test } from '@playwright/test';
import { login, logout, uniqueLabel, waitForLivewireIdle } from './support/app';
import { loadFixtures } from './support/fixtures';

test('player proposes an activity from the event plan', async ({ page }) => {
    const fixtures = loadFixtures();
    const proposedName = uniqueLabel('E2E Proposed Activity');

    await login(page, fixtures.player.email, fixtures.player.password);
    await page.goto(`${fixtures.event.path}?tab=plan`);
    await waitForLivewireIdle(page);

    await page.locator('[data-ui="event-show-propose"]').click();
    await page.waitForURL(/\/activities\/create/);
    await waitForLivewireIdle(page);

    await page.locator('[data-activity-name-input]').fill(proposedName);
    await page.locator('[data-ui="activity-submit"]').click();
    await page.waitForURL(new RegExp(`/events/${fixtures.event.slug}`));
    await waitForLivewireIdle(page);

    await logout(page);
    await login(page, fixtures.organizer.email, fixtures.organizer.password);
    await page.goto(`${fixtures.event.path}?tab=proposals`);
    await waitForLivewireIdle(page);

    await expect(page.locator('[data-ui="event-show-pending-proposals"]')).toContainText(proposedName);
});

test('organizer accepts a pending proposal onto an empty slot', async ({ page }) => {
    const fixtures = loadFixtures();

    await login(page, fixtures.organizer.email, fixtures.organizer.password);
    await page.goto(`${fixtures.event.path}?tab=proposals`);
    await waitForLivewireIdle(page);

    const row = page
        .locator('[data-ui="event-show-pending-proposals"] li')
        .filter({ hasText: fixtures.pending_proposal.activity_name });
    await expect(row).toBeVisible({ timeout: 30_000 });
    await row.locator('[data-ui="event-show-proposal-accept"]').click();
    await waitForLivewireIdle(page);

    await expect(page.locator('[data-ui="event-show-pending-proposals"]')).not.toContainText(
        fixtures.pending_proposal.activity_name,
    );

    await page.goto(`${fixtures.event.path}?tab=plan`);
    await waitForLivewireIdle(page);
    await expect(page.locator('[data-ui="event-show-slots"]')).toContainText(fixtures.pending_proposal.activity_name);
});
