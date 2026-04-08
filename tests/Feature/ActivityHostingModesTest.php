<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use App\Services\ActivityHostingModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityHostingModesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function self_hosted_to_proposed_clears_schedule_and_place(): void
    {
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'place_id' => Place::create(['name' => 'Room A', 'type' => 'venue', 'is_online' => false])->id,
        ]);

        app(ActivityHostingModeService::class)->moveSelfHostedToProposed($activity);
        $activity->refresh();

        $this->assertSame(Activity::HOSTING_MODE_PROPOSED_TO_EVENT, $activity->hosting_mode);
        $this->assertNull($activity->place_id);
        $this->assertNull($activity->starts_at);
        $this->assertNull($activity->ends_at);
    }

    #[Test]
    public function self_hosted_to_proposed_is_blocked_with_participants(): void
    {
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => User::factory()->create()->id,
            'is_absent' => false,
        ]);

        $this->expectException(ValidationException::class);
        app(ActivityHostingModeService::class)->moveSelfHostedToProposed($activity);
    }

    #[Test]
    public function attached_scope_includes_self_hosted_and_public_scheduled_only(): void
    {
        $selfHosted = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
        ]);
        $draft = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ]);

        $publicEvent = Event::factory()->create(['is_public' => true]);
        $privateEvent = Event::factory()->create(['is_public' => false]);
        $scheduledPublic = Activity::factory()->create(['hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT]);
        $scheduledPrivate = Activity::factory()->create(['hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT]);
        Slot::factory()->create(['event_id' => $publicEvent->id, 'activity_id' => $scheduledPublic->id]);
        Slot::factory()->create(['event_id' => $privateEvent->id, 'activity_id' => $scheduledPrivate->id]);

        $ids = Activity::query()->attachedToPublicEvent()->pluck('id')->all();

        $this->assertContains($selfHosted->id, $ids);
        $this->assertContains($scheduledPublic->id, $ids);
        $this->assertNotContains($draft->id, $ids);
        $this->assertNotContains($scheduledPrivate->id, $ids);
    }
}
