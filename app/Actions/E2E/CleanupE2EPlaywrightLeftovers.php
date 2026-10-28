<?php

declare(strict_types=1);

namespace App\Actions\E2E;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Support\Collection;

final class CleanupE2EPlaywrightLeftovers
{
    /**
     * Remove rows created by Playwright specs while keeping deterministic fixture entities.
     */
    public function __invoke(): void
    {
        $userIds = $this->fixtureUserIds();
        if ($userIds->isEmpty()) {
            return;
        }

        $this->deleteNonFixtureEvents($userIds);
        $this->deleteNonFixtureActivities($userIds);
        $this->deleteNonFixtureSlotsOnJoinEvent();
    }

    /**
     * @return Collection<int, int>
     */
    private function fixtureUserIds(): Collection
    {
        return User::query()
            ->whereIn('email', [
                PrepareE2EFixtures::ORGANIZER_EMAIL,
                PrepareE2EFixtures::PLAYER_EMAIL,
            ])
            ->pluck('id');
    }

    /**
     * @param  Collection<int, int>  $userIds
     */
    private function deleteNonFixtureEvents(Collection $userIds): void
    {
        Event::query()
            ->withTrashed()
            ->whereIn('created_by', $userIds)
            ->where('slug', '!=', PrepareE2EFixtures::EVENT_SLUG)
            ->each(fn (Event $event) => $event->forceDelete());
    }

    /**
     * @param  Collection<int, int>  $userIds
     */
    private function deleteNonFixtureActivities(Collection $userIds): void
    {
        Activity::query()
            ->withTrashed()
            ->whereIn('created_by', $userIds)
            ->whereNotIn('slug', PrepareE2EFixtures::protectedActivitySlugs())
            ->each(fn (Activity $activity) => $activity->forceDelete());
    }

    private function deleteNonFixtureSlotsOnJoinEvent(): void
    {
        $event = Event::query()
            ->where('slug', PrepareE2EFixtures::EVENT_SLUG)
            ->first();

        if ($event === null) {
            return;
        }

        Slot::query()
            ->withTrashed()
            ->where('event_id', $event->id)
            ->each(fn (Slot $slot) => $slot->forceDelete());
    }
}
