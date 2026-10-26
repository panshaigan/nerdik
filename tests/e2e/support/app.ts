import path from 'node:path';

import { expect, type Locator, type Page } from '@playwright/test';

export async function waitForLivewireIdle(page: Page): Promise<void> {
    await expect(page.locator('html')).not.toHaveClass(/ui-navigating/, { timeout: 20_000 });
}

export async function logout(page: Page): Promise<void> {
    await page.context().clearCookies();
    await page.goto('/');
    await waitForLivewireIdle(page);
}

export async function login(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/login');
    await page.locator('[data-ui="auth-login-email"]').fill(email);
    await page.locator('[data-ui="auth-login-password"]').fill(password);
    await page.locator('[data-ui="auth-login-submit"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 20_000 });
    await waitForLivewireIdle(page);
}

export async function searchFor(page: Page, query: string): Promise<void> {
    const input = page.locator('[data-ts-input]').first();
    await input.click();
    await input.fill('');
    await input.pressSequentially(query, { delay: 40 });
    await input.press('Enter');
    await waitForLivewireIdle(page);
}

export async function saveCurrentSearch(page: Page): Promise<void> {
    await page.locator('[data-ui="browse-filters-toggle"]').click();
    await expect(page.locator('[data-ui="browse-events-filters-panel"]')).toBeVisible();
    await page.locator('[data-ui="browse-events-save-search"]').click();
    await expect(page.getByText('Search saved')).toBeVisible();
    await page.keyboard.press('Escape');
}

export function e2eFixturePath(filename: string): string {
    return path.join(process.cwd(), 'tests', 'e2e', 'fixtures', filename);
}

export async function setEventLogoSourceUpload(page: Page): Promise<void> {
    await page.evaluate(async () => {
        const host = document.querySelector('form[data-event-form]')?.closest('[wire\\:id]');
        const wireId = host?.getAttribute('wire:id');
        if (!wireId || typeof window.Livewire === 'undefined') {
            throw new Error('Manage event Livewire host not found');
        }
        const component = window.Livewire.find(wireId);
        await component?.set('tab', 'image');
        await component?.set('logo_source', 'upload');
    });
    await waitForLivewireIdle(page);
}

export async function cropAndApply(page: Page, filePath: string): Promise<void> {
    const dropzone = page.locator('[data-image-crop-dropzone]');
    await expect(dropzone).toBeVisible({ timeout: 20_000 });

    const fileInput = dropzone.locator('[data-image-crop-file]');
    await fileInput.setInputFiles(filePath);

    const modal = page.locator('#ui-image-crop-modal');
    await expect(modal).toBeVisible();
    await expect(page.locator('[data-image-crop-apply]')).toBeEnabled();
    await page.locator('[data-image-crop-apply]').click();
    await expect(modal).toBeHidden();
}

export async function selectSavedPlace(page: Page, placeName: string): Promise<void> {
    await expect(page.locator('[data-ep-map]')).toHaveClass(/leaflet-container/);

    await page.getByRole('button', { name: placeName, exact: true }).click();
    await expect(page.locator('[data-ep-place-ids] input[name="place_ids[]"]')).toHaveCount(1);

    await expect.poll(async () => page.evaluate(() => {
        const host = document.querySelector('[data-event-places-unified]')?.closest('[wire\\:id]');
        if (!host || typeof window.Livewire === 'undefined') {
            return 0;
        }

        const ids = window.Livewire.find(host.getAttribute('wire:id') ?? '')?.get('place_ids');

        return Array.isArray(ids) ? ids.length : 0;
    })).toBeGreaterThan(0);
}

export async function setDatetime(page: Page, locator: Locator, isoLocal: string): Promise<void> {
    await locator.click();
    await page.locator('.flatpickr-calendar.open').waitFor();
    await locator.evaluate((el, value) => {
        const input = el instanceof HTMLInputElement
            ? el
            : el.querySelector('input[data-ui-datetime]');
        if (!(input instanceof HTMLInputElement)) {
            throw new Error('Datetime input not found');
        }
        const picker = (input as HTMLInputElement & { _flatpickr?: { setDate: (v: string, trigger: boolean) => void; close: () => void } })._flatpickr;
        if (picker) {
            picker.setDate(value, true);
            picker.close();
            return;
        }
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }, isoLocal);
}

export function uniqueLabel(prefix: string): string {
    return `${prefix} ${Date.now()}`;
}

export function futureDatetimeRange(): { start: string; end: string } {
    const start = new Date();
    start.setDate(start.getDate() + 10);
    start.setHours(12, 0, 0, 0);
    const end = new Date(start);
    end.setDate(end.getDate() + 1);
    end.setHours(18, 0, 0, 0);

    const pad = (n: number) => String(n).padStart(2, '0');
    const fmt = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;

    return { start: fmt(start), end: fmt(end) };
}
