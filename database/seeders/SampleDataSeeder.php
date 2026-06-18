<?php

namespace Database\Seeders;

use App\Actions\Seeders\AttachGameTagChainUntilGenre;
use App\Actions\Seeders\ResolveParticipantBoundsForSlot;
use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\User;
use Database\Factories\DatabaseNotificationFactory;
use Database\Factories\SampleNotificationContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Random\RandomException;

use function fake;

/**
 * Generate sample/test data\
 **/
class SampleDataSeeder extends Seeder
{
    public const DATASET_MINIMAL = 1;

    public const DATASET_STANDARD = 2;

    public const DATASET_MAXIMAL = 3;

    private const MAX_PROPOSED_PER_EVENT = 3;

    public const DATASETS = [
        self::DATASET_MINIMAL => [
            'admins' => 1,
            'organizers' => 2,
            'standardUsers' => 10,
            'organizations' => 10,
            'places' => 10,
            'maxRoomsPerVenue' => 2,
            'events' => 5,
            'minSlotsPerEvent' => 6,
            'maxSlotsPerEvent' => 10,
            'selfHostedActivities' => 10,
            'draftActivities' => 10,
            'scheduledActivities' => 100,
            'proposedActivities' => 50,
            'notificationsPerUserMin' => 8,
            'notificationsPerUserMax' => 15,
        ],
        self::DATASET_STANDARD => [
            'admins' => 2,
            'organizers' => 4,
            'standardUsers' => 20,
            'organizations' => 20,
            'places' => 20,
            'maxRoomsPerVenue' => 4,
            'events' => 10,
            'minSlotsPerEvent' => 6,
            'maxSlotsPerEvent' => 20,
            'selfHostedActivities' => 20,
            'draftActivities' => 20,
            'scheduledActivities' => 60,
            'proposedActivities' => 100,
            'notificationsPerUserMin' => 15,
            'notificationsPerUserMax' => 30,
        ],
        self::DATASET_MAXIMAL => [
            'admins' => 3,
            'organizers' => 8,
            'standardUsers' => 100,
            'organizations' => 30,
            'places' => 30,
            'maxRoomsPerVenue' => 6,
            'events' => 20,
            'minSlotsPerEvent' => 6,
            'maxSlotsPerEvent' => 30,
            'selfHostedActivities' => 30,
            'draftActivities' => 20,
            'scheduledActivities' => 150,
            'proposedActivities' => 60,
            'notificationsPerUserMin' => 30,
            'notificationsPerUserMax' => 50,
        ],
    ];

    public static function resolveDatasetFromEnv(): int
    {
        $raw = getenv('SEED_DATASET');
        $value = strtolower((string) ($raw !== false ? $raw : env('SEED_DATASET', 'minimal')));

        return match ($value) {
            'standard' => self::DATASET_STANDARD,
            'maximal' => self::DATASET_MAXIMAL,
            default => self::DATASET_MINIMAL,
        };
    }

    /**
     * Seed sample data for local testing: users, orgs, events, slots, activities, proposals.
     * All entities get created_by set. Safe to run multiple times (use firstOrCreate by slug/email).
     *
     * @throws RandomException
     */
    public function run(int $chosenDataset = self::DATASET_MINIMAL): void
    {
        $dataset = self::DATASETS[$chosenDataset];
        $this->callWith(UserSeeder::class, ['dataset' => $dataset]);
        $this->callWith(PlaceSeeder::class, ['dataset' => $dataset]);

        $activityTypes = ActivityType::where('slug', ActivityType::SLUG_RPG)->get();
        $organizers = User::where('is_event_organizer', 1)->get();
        $allUsers = User::all();
        $venues = Place::where('type', Place::TYPE_VENUE)->get();
        $gameTags = Tag::query()->games()->get();
        $formatTags = Tag::query()->formats()->get();
        $otherTags = Tag::query()->others()->get();
        $triggerTags = Tag::query()->triggers()->get();

        $organizations = Organization::factory($dataset['organizations'])
            ->recycle($allUsers)
            ->predefined()
            ->create();

        $events = Event::factory($dataset['events'])
            ->public()
            ->recycle($organizations)
            ->recycle($organizers)
            ->recycle($venues)
            ->predefined()
            ->withSameCreatorAsOrganization()
            ->has(EventEnrollmentWindow::factory()->consistentWithEvent())
            ->withRandomSlotsPerEvent($dataset['minSlotsPerEvent'], $dataset['maxSlotsPerEvent'], $activityTypes)
            ->withVenues($venues)
            ->withRandomRooms()
            ->create();

        $events->load(['slots.activityTypes']);

        $selfHostedActivities = Activity::factory($dataset['selfHostedActivities'])
            ->recycle($allUsers)
            ->predefined()
            ->selfHosted($allUsers)
            ->create();

        Activity::factory($dataset['draftActivities'])
            ->recycle($allUsers)
            ->predefined()
            ->create();

        $proposedCount = min(
            $dataset['proposedActivities'],
            $events->count() * self::MAX_PROPOSED_PER_EVENT,
        );

        $proposedActivities = Activity::factory($proposedCount)
            ->recycle($allUsers)
            ->predefined()
            ->proposed()
            ->create();

        $fillBudgetsByEventId = $this->resolveScheduledFillBudgetsByEvent($events);
        $fillBudgetsByEventId = $this->capScheduledFillBudgets($fillBudgetsByEventId, $dataset['scheduledActivities']);
        $scheduledCount = array_sum($fillBudgetsByEventId);

        $scheduledActivities = Activity::factory($scheduledCount)
            ->recycle($allUsers)
            ->predefined()
            ->scheduled()
            ->create();

        $this->assignSelfHostedProposals($selfHostedActivities, $events);
        $this->assignProposedActivities($proposedActivities, $events);
        $this->assignScheduledActivities($scheduledActivities, $events, $fillBudgetsByEventId);

        $activities = $proposedActivities->merge($scheduledActivities)->merge($selfHostedActivities);

        foreach ($activities as $activity) {
            $this->attachActivityTags($activity, $gameTags, $formatTags, $otherTags, $triggerTags);
        }

        foreach ($allUsers as $user) {
            $user->interestedActivities()->attach($activities->random(fake()->numberBetween(0, 10)));
            $user->interestedEvents()->attach($events->random(fake()->numberBetween(0, 2)));
        }

        $this->seedNotifications($dataset, $allUsers, $activities, $events);
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, int>
     */
    private function resolveScheduledFillBudgetsByEvent(Collection $events): array
    {
        /** @var array<int, int> $budgets */
        $budgets = [];

        foreach ($events as $event) {
            $slotCount = $event->slots->count();

            if ($slotCount <= 1) {
                $budgets[$event->id] = 0;

                continue;
            }

            $minFill = max(1, (int) floor($slotCount * 0.3));
            $maxFill = $slotCount - 1;

            $budgets[$event->id] = fake()->numberBetween($minFill, $maxFill);
        }

        return $budgets;
    }

    /**
     * @param  array<int, int>  $budgets
     * @return array<int, int>
     */
    private function capScheduledFillBudgets(array $budgets, int $cap): array
    {
        while (array_sum($budgets) > $cap) {
            $reducibleEventIds = collect($budgets)
                ->filter(fn (int $budget): bool => $budget > 0)
                ->keys()
                ->all();

            if ($reducibleEventIds === []) {
                break;
            }

            $eventId = fake()->randomElement($reducibleEventIds);
            $budgets[$eventId]--;
        }

        return $budgets;
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, Event>  $events
     */
    private function assignSelfHostedProposals(Collection $activities, Collection $events): void
    {
        foreach ($activities as $activity) {
            ActivityProposal::factory()
                ->recycle($events->random())
                ->recycle($activity)
                ->recycle($activity->creator)
                ->alignWithActivity($activity)
                ->create();
        }
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, Event>  $events
     */
    private function assignProposedActivities(Collection $activities, Collection $events): void
    {
        /** @var array<int, int> $proposalCountsByEventId */
        $proposalCountsByEventId = $events
            ->mapWithKeys(fn (Event $event): array => [$event->id => 0])
            ->all();

        foreach ($activities->shuffle() as $activity) {
            $eligibleEvents = $events->filter(
                fn (Event $event): bool => ($proposalCountsByEventId[$event->id] ?? 0) < self::MAX_PROPOSED_PER_EVENT,
            );

            if ($eligibleEvents->isEmpty()) {
                break;
            }

            $event = $eligibleEvents->random();
            $proposalCountsByEventId[$event->id]++;

            ActivityProposal::factory()
                ->for($event)
                ->for($activity)
                ->for($activity->creator, 'creator')
                ->create([
                    'status' => ActivityProposalStatus::Pending,
                ]);
        }
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, Event>  $events
     * @param  array<int, int>  $fillBudgetsByEventId
     */
    private function assignScheduledActivities(
        Collection $activities,
        Collection $events,
        array $fillBudgetsByEventId,
    ): void {
        $resolveParticipantBounds = app(ResolveParticipantBoundsForSlot::class);

        /** @var list<int> $usedSlotIds */
        $usedSlotIds = [];

        /** @var array<int, int> $remainingFillBudgetsByEventId */
        $remainingFillBudgetsByEventId = $fillBudgetsByEventId;

        $freeSlots = $events
            ->flatMap(fn (Event $event) => $event->slots)
            ->filter(fn (Slot $slot): bool => $slot->activity_id === null)
            ->values();

        foreach ($activities->shuffle() as $activity) {
            $candidateSlots = $freeSlots->filter(function (Slot $slot) use (
                $activity,
                $usedSlotIds,
                $resolveParticipantBounds,
                $remainingFillBudgetsByEventId,
            ): bool {
                if (($remainingFillBudgetsByEventId[$slot->event_id] ?? 0) <= 0) {
                    return false;
                }

                if (in_array($slot->id, $usedSlotIds, true)) {
                    return false;
                }

                $trialActivity = $activity->replicate();
                $trialActivity->fill($resolveParticipantBounds($slot, $activity));

                return $slot->fitsProposalActivity($trialActivity);
            });

            if ($candidateSlots->isEmpty()) {
                continue;
            }

            $slot = $candidateSlots->random();
            $activity->update($resolveParticipantBounds($slot, $activity));

            ActivityProposal::factory()
                ->for($slot->event)
                ->for($activity)
                ->for($activity->creator, 'creator')
                ->create([
                    'status' => ActivityProposalStatus::Accepted,
                    'accepted_slot_id' => $slot->id,
                ]);

            $slot->update([
                'activity_id' => $activity->id,
            ]);

            $usedSlotIds[] = $slot->id;
            $remainingFillBudgetsByEventId[$slot->event_id]--;
        }
    }

    /**
     * @param  Collection<int, Tag>  $gameTags
     * @param  Collection<int, Tag>  $formatTags
     * @param  Collection<int, Tag>  $otherTags
     * @param  Collection<int, Tag>  $triggerTags
     */
    private function attachActivityTags(
        Activity $activity,
        Collection $gameTags,
        Collection $formatTags,
        Collection $otherTags,
        Collection $triggerTags,
    ): void {
        $activity->tags()->attach($otherTags->random(1));
        $activity->tags()->attach($formatTags->random(1));
        $activity->tags()->attach($triggerTags->random(fake()->numberBetween(1, 3)));

        app(AttachGameTagChainUntilGenre::class)($activity, $gameTags->random());
    }

    /**
     * @param  array<string, int>  $dataset
     * @param  Collection<int, User>  $allUsers
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, Event>  $events
     */
    private function seedNotifications(array $dataset, Collection $allUsers, Collection $activities, Collection $events): void
    {
        $proposals = ActivityProposal::with(['activity', 'event'])->get();

        if ($proposals->isEmpty() || $activities->isEmpty() || $events->isEmpty()) {
            return;
        }

        DB::table('notifications')->truncate();

        $context = new SampleNotificationContext(
            proposals: $proposals,
            activities: $activities,
            events: $events,
            users: $allUsers,
        );

        foreach ($allUsers as $user) {
            $count = fake()->numberBetween(
                $dataset['notificationsPerUserMin'],
                $dataset['notificationsPerUserMax'],
            );

            for ($i = 0; $i < $count; $i++) {
                DatabaseNotificationFactory::new()
                    ->for($user, 'notifiable')
                    ->randomSampleNotification($context, $user)
                    ->create();
            }
        }
    }
}
