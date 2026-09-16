<?php

namespace Tests\Feature;

use App\Models\ActivityType;
use App\Models\Event;
use App\Models\User;
use App\Services\SlotFormService;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityTypeParticipantCapacityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seeder_sets_slug_specific_participant_limits(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $this->assertSame(20, ActivityType::findBySlug(ActivityType::SLUG_RPG)?->max_participants_limit);
        $this->assertSame(8, ActivityType::findBySlug(ActivityType::SLUG_BOARD)?->max_participants_limit);
        $this->assertSame(100, ActivityType::findBySlug(ActivityType::SLUG_LECTURE)?->max_participants_limit);
    }

    #[Test]
    public function max_participants_limit_for_id_falls_back_when_missing(): void
    {
        $this->assertSame(
            ActivityType::DEFAULT_MAX_PARTICIPANTS_LIMIT,
            ActivityType::maxParticipantsLimitForId(null)
        );

        $type = ActivityType::factory()->create(['max_participants_limit' => 42]);

        $this->assertSame(42, ActivityType::maxParticipantsLimitForId((int) $type->id));
    }

    #[Test]
    public function slot_mass_create_accepts_non_rpg_activity_types(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::factory()->create([
            'created_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $boardTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_BOARD)?->id;

        $request = Request::create('/slots/mass', 'POST', [
            'event_id' => $event->id,
            'base_name' => 'Board',
            'count' => 1,
            'requires_approval' => 1,
            'activity_types' => [$boardTypeId],
        ]);

        app(SlotFormService::class)->performMassCreate($request);

        $slot = $event->slots()->first();
        $this->assertNotNull($slot);
        $this->assertSame([$boardTypeId], $slot->activity_types_ids);
    }
}
