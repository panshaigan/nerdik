<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecalculateTagPopularityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sets_popularity_from_upcoming_browse_visible_activities(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(4),
        ]);

        $popularTag = Tag::factory()->create();
        $quietTag = Tag::factory()->create();

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);
        $activity->tags()->attach($popularTag->id);

        $this->artisan('tags:recalculate-popularity')->assertSuccessful();

        $this->assertSame(1, $popularTag->fresh()->popularity_score);
        $this->assertSame(0, $quietTag->fresh()->popularity_score);
    }

    public function test_command_ignores_activities_on_non_public_events(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $owner->id,
            'is_public' => false,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(4),
        ]);

        $tag = Tag::factory()->create();
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        Slot::factory()->create(['event_id' => $event->id, 'activity_id' => $activity->id]);
        $activity->tags()->attach($tag->id);

        $this->artisan('tags:recalculate-popularity')->assertSuccessful();

        $this->assertSame(0, $tag->fresh()->popularity_score);
    }

    public function test_command_ignores_ended_self_hosted_activities(): void
    {
        $owner = User::factory()->create();
        $tag = Tag::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->subDay(),
        ]);
        $activity->tags()->attach($tag->id);

        $this->artisan('tags:recalculate-popularity')->assertSuccessful();

        $this->assertSame(0, $tag->fresh()->popularity_score);
    }
}
