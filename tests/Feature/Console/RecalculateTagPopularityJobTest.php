<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Jobs\RecalculateTagPopularityJob;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RecalculateTagPopularityJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_recalculates_only_the_provided_tag_ids(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(4),
        ]);

        $popularTag = Tag::factory()->create(['popularity_score' => 0]);
        $quietTag = Tag::factory()->create(['popularity_score' => 5]);
        $untouchedTag = Tag::factory()->create(['popularity_score' => 7]);

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

        $activity->tags()->attach([$popularTag->id, $untouchedTag->id]);

        (new RecalculateTagPopularityJob([$popularTag->id, $quietTag->id]))->handle(app('App\Services\TagPopularityRecalculator'));

        $this->assertSame(1, $popularTag->fresh()->popularity_score);
        $this->assertSame(0, $quietTag->fresh()->popularity_score);
        $this->assertSame(7, $untouchedTag->fresh()->popularity_score);
    }
}
