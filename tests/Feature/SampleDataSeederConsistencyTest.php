<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Slot;
use App\Models\TagCategory;
use Database\Seeders\BaseDataSeeder;
use Database\Seeders\PlaceSeeder;
use Database\Seeders\SampleDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SampleDataSeederConsistencyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_caps_proposed_activities_at_three_per_event(): void
    {
        $this->seedSampleData();

        Event::query()->each(function (Event $event): void {
            $proposedCount = ActivityProposal::query()
                ->where('event_id', $event->id)
                ->whereHas('activity', fn ($query) => $query->where(
                    'hosting_mode',
                    Activity::HOSTING_MODE_PROPOSED_TO_EVENT,
                ))
                ->count();

            $this->assertLessThanOrEqual(3, $proposedCount, "Event {$event->id} has too many proposed activities.");
        });
    }

    #[Test]
    public function every_sample_activity_has_required_tags(): void
    {
        $this->seedSampleData();

        Activity::query()->each(function (Activity $activity): void {
            foreach ([TagCategory::KEY_GAME, TagCategory::KEY_GENRE, TagCategory::KEY_MECHANIC] as $categoryKey) {
                $this->assertTrue(
                    $activity->tags()
                        ->whereHas('tagCategory', fn ($query) => $query->where('key', $categoryKey))
                        ->exists(),
                    "Activity {$activity->id} is missing a {$categoryKey} tag.",
                );
            }
        });
    }

    #[Test]
    public function no_sample_self_hosted_activity_is_untagged(): void
    {
        $this->seedSampleData();

        Activity::query()
            ->where('hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
            ->each(function (Activity $activity): void {
                $this->assertGreaterThan(
                    0,
                    $activity->tags()->count(),
                    "Self-hosted activity {$activity->id} has no tags attached.",
                );
            });
    }

    #[Test]
    public function majority_of_joinable_activities_have_participants(): void
    {
        $this->seedSampleData();

        $joinableActivities = Activity::query()
            ->where(function ($query): void {
                $query->where('hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
                    ->orWhere(function ($scheduled): void {
                        $scheduled
                            ->where('hosting_mode', Activity::HOSTING_MODE_SCHEDULED_ON_EVENT)
                            ->whereHas('slot');
                    });
            })
            ->get();

        $this->assertNotEmpty($joinableActivities);

        $withParticipants = $joinableActivities->filter(
            fn (Activity $activity): bool => $activity->participants()->where('is_absent', false)->exists(),
        );

        $this->assertGreaterThanOrEqual(
            (int) ceil($joinableActivities->count() * 0.8),
            $withParticipants->count(),
        );

        Activity::query()
            ->where('hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
            ->each(function (Activity $activity): void {
                $this->assertTrue(
                    $activity->participants()->where('is_absent', false)->exists(),
                    "Self-hosted activity {$activity->id} has no participants.",
                );
            });
    }

    #[Test]
    public function proposed_and_draft_activities_have_no_participants(): void
    {
        $this->seedSampleData();

        Activity::query()
            ->whereIn('hosting_mode', [
                Activity::HOSTING_MODE_DRAFT,
                Activity::HOSTING_MODE_PROPOSED_TO_EVENT,
            ])
            ->each(function (Activity $activity): void {
                $this->assertSame(
                    0,
                    $activity->participants()->where('is_absent', false)->count(),
                    "Activity {$activity->id} should not have participants.",
                );
            });
    }

    #[Test]
    public function self_hosted_participant_counts_are_higher(): void
    {
        $this->seedSampleData();

        $counts = Activity::query()
            ->where('hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
            ->withCount(['participants as active_participants_count' => fn ($query) => $query->where('is_absent', false)])
            ->pluck('active_participants_count')
            ->sort()
            ->values();

        $this->assertNotEmpty($counts);

        $median = $this->median($counts);

        $this->assertGreaterThanOrEqual(3, $median);
    }

    #[Test]
    public function it_assigns_scheduled_activities_to_unique_slots_with_matching_capacity(): void
    {
        $this->seedSampleData();

        $scheduledProposals = ActivityProposal::query()
            ->whereNotNull('accepted_slot_id')
            ->whereHas('activity', fn ($query) => $query->where(
                'hosting_mode',
                Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
            ))
            ->with(['activity', 'acceptedSlot'])
            ->get();

        $this->assertNotEmpty($scheduledProposals);

        $acceptedSlotIds = [];

        foreach ($scheduledProposals as $proposal) {
            $slot = $proposal->acceptedSlot;
            $activity = $proposal->activity;

            $this->assertNotNull($slot);
            $this->assertNotNull($activity);
            $this->assertSame($activity->id, $slot->activity_id);
            $this->assertTrue($slot->fitsProposalActivity($activity));
            $this->assertNotContains($slot->id, $acceptedSlotIds);

            $acceptedSlotIds[] = $slot->id;
        }
    }

    #[Test]
    public function it_creates_per_event_slot_counts_within_dataset_bounds(): void
    {
        $this->seedSampleData();

        $dataset = SampleDataSeeder::DATASETS[SampleDataSeeder::DATASET_MINIMAL];

        Event::query()->withCount('slots')->each(function (Event $event) use ($dataset): void {
            $this->assertGreaterThanOrEqual(
                $dataset['minSlotsPerEvent'],
                $event->slots_count,
                "Event {$event->id} has too few slots.",
            );
            $this->assertLessThanOrEqual(
                $dataset['maxSlotsPerEvent'],
                $event->slots_count,
                "Event {$event->id} has too many slots.",
            );
        });
    }

    #[Test]
    public function it_leaves_some_slots_unfilled(): void
    {
        $this->seedSampleData();

        $totalSlots = Slot::query()->count();
        $filledSlots = Slot::query()->whereNotNull('activity_id')->count();

        $this->assertGreaterThan(1, $totalSlots);
        $this->assertLessThan($totalSlots, $filledSlots);
    }

    #[Test]
    public function it_varies_slot_counts_across_events(): void
    {
        $this->seedSampleData();

        $slotCounts = Event::query()
            ->withCount('slots')
            ->pluck('slots_count');

        $this->assertGreaterThan(1, $slotCounts->unique()->count());
    }

    #[Test]
    public function it_leaves_at_least_one_empty_slot_per_event_with_multiple_slots(): void
    {
        $this->seedSampleData();

        $events = Event::query()
            ->withCount([
                'slots',
                'slots as filled_slots_count' => fn ($query) => $query->whereNotNull('activity_id'),
            ])
            ->get()
            ->filter(fn (Event $event): bool => $event->slots_count >= 2);

        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            $this->assertLessThan(
                $event->slots_count,
                $event->filled_slots_count,
                "Event {$event->id} should have at least one empty slot.",
            );
        }
    }

    #[Test]
    public function it_resolves_dataset_from_environment_variable(): void
    {
        $this->assertSame(SampleDataSeeder::DATASET_MINIMAL, SampleDataSeeder::resolveDatasetFromEnv());

        putenv('SEED_DATASET=standard');
        $this->assertSame(SampleDataSeeder::DATASET_STANDARD, SampleDataSeeder::resolveDatasetFromEnv());

        putenv('SEED_DATASET=maximal');
        $this->assertSame(SampleDataSeeder::DATASET_MAXIMAL, SampleDataSeeder::resolveDatasetFromEnv());

        putenv('SEED_DATASET=unknown');
        $this->assertSame(SampleDataSeeder::DATASET_MINIMAL, SampleDataSeeder::resolveDatasetFromEnv());

        putenv('SEED_DATASET');
    }

    #[Test]
    public function it_keeps_activity_slugs_unique_when_seeding_maximal_dataset(): void
    {
        $this->seedSampleDependencies(SampleDataSeeder::DATASET_MAXIMAL);

        $this->app->make(SampleDataSeeder::class)->run(SampleDataSeeder::DATASET_MAXIMAL);

        $slugs = Activity::query()->pluck('slug')->all();

        $this->assertGreaterThanOrEqual(200, count($slugs));
        $this->assertSame(count($slugs), count(array_unique($slugs)));
    }

    #[Test]
    public function it_suffixes_activity_slugs_when_more_predefined_activities_are_seeded_later(): void
    {
        $this->seedSampleDependencies(SampleDataSeeder::DATASET_MINIMAL);

        $this->app->make(SampleDataSeeder::class)->run(SampleDataSeeder::DATASET_MINIMAL);

        $beforeCount = Activity::query()->count();

        Activity::factory(5)->predefined()->create();

        $slugs = Activity::query()->pluck('slug')->all();

        $this->assertGreaterThan($beforeCount, Activity::query()->count());
        $this->assertSame(count($slugs), count(array_unique($slugs)));
    }

    private function seedSampleData(): void
    {
        $this->seedSampleDependencies(SampleDataSeeder::DATASET_MINIMAL);

        $this->app->make(SampleDataSeeder::class)->run(SampleDataSeeder::DATASET_MINIMAL);
    }

    private function seedSampleDependencies(int $datasetKey): void
    {
        $dataset = SampleDataSeeder::DATASETS[$datasetKey];

        $this->seed(BaseDataSeeder::class);
        $this->app->make(UserSeeder::class)->run($dataset);
        $this->app->make(PlaceSeeder::class)->run($dataset);
    }

    /**
     * @param  Collection<int, int>  $values
     */
    private function median(Collection $values): float
    {
        $count = $values->count();
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $values->get($middle);
        }

        return ((float) $values->get($middle - 1) + (float) $values->get($middle)) / 2;
    }
}
