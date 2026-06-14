<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use Database\Seeders\BaseDataSeeder;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->seed(BaseDataSeeder::class);

        $this->app->make(SampleDataSeeder::class)->run(SampleDataSeeder::DATASET_MAXIMAL);

        $slugs = Activity::query()->pluck('slug')->all();

        $this->assertGreaterThanOrEqual(200, count($slugs));
        $this->assertSame(count($slugs), count(array_unique($slugs)));
    }

    #[Test]
    public function it_suffixes_activity_slugs_when_more_predefined_activities_are_seeded_later(): void
    {
        $this->seed(BaseDataSeeder::class);

        $this->app->make(SampleDataSeeder::class)->run(SampleDataSeeder::DATASET_MINIMAL);

        $beforeCount = Activity::query()->count();

        Activity::factory(5)->predefined()->create();

        $slugs = Activity::query()->pluck('slug')->all();

        $this->assertGreaterThan($beforeCount, Activity::query()->count());
        $this->assertSame(count($slugs), count(array_unique($slugs)));
    }

    private function seedSampleData(): void
    {
        $this->seed(BaseDataSeeder::class);

        $this->app->make(SampleDataSeeder::class)->run(SampleDataSeeder::DATASET_MINIMAL);
    }
}
