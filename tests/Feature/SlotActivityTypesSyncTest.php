<?php

namespace Tests\Feature;

use App\Models\ActivityType;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlotActivityTypesSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_slot_activity_types_does_not_delete_activity_types(): void
    {
        $slot = Slot::factory()->create();
        $firstType = ActivityType::factory()->create();
        $secondType = ActivityType::factory()->create();

        $slot->setActivityTypes([(int) $firstType->id, (int) $secondType->id]);

        $this->assertDatabaseHas('activity_types', ['id' => $firstType->id]);
        $this->assertDatabaseHas('activity_types', ['id' => $secondType->id]);
        $this->assertSame(
            [(int) $firstType->id, (int) $secondType->id],
            $slot->fresh()->activity_types_ids
        );
    }
}
