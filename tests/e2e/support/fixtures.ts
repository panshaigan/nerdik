import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';

export type E2EFixtures = {
    player: { email: string; password: string };
    organizer: { email: string; password: string };
    event: { name: string; slug: string; path: string; edit_path: string };
    activity: { name: string; slug: string; path: string };
    slot: { id: number; name: string };
    empty_slot: { id: number; name: string };
    waitlist_activity: { name: string; slug: string; path: string };
    draft_activity: { name: string; slug: string; path: string };
    pending_proposal: { activity_name: string; activity_slug: string; proposal_id: number };
    notification: { id: string; expected_path: string };
    place: { name: string; latitude: number; longitude: number };
    map: { path: string };
};

export function fixtureFilePath(): string {
    return path.join(process.cwd(), 'storage', 'app', 'e2e-fixtures.json');
}

export function loadFixtures(): E2EFixtures {
    const filePath = fixtureFilePath();

    if (!existsSync(filePath)) {
        throw new Error(
            `Missing ${filePath}. Run \`php artisan e2e:prepare\` (or \`vendor/bin/sail artisan e2e:prepare\`) first.`,
        );
    }

    return JSON.parse(readFileSync(filePath, 'utf8')) as E2EFixtures;
}
