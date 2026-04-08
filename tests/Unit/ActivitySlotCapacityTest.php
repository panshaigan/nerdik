<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\Slot;
use PHPUnit\Framework\TestCase;

class ActivitySlotCapacityTest extends TestCase
{
    public function test_physical_headcount_adds_active_host(): void
    {
        $a = new Activity([
            'max_participants' => 5,
            'is_host_passive' => false,
        ]);

        $this->assertSame(6, $a->physicalHeadcountForSlotCapacity());
    }

    public function test_physical_headcount_passive_host_does_not_add_seat(): void
    {
        $a = new Activity([
            'max_participants' => 5,
            'is_host_passive' => true,
        ]);

        $this->assertSame(5, $a->physicalHeadcountForSlotCapacity());
    }

    public function test_physical_headcount_null_when_max_participants_unset(): void
    {
        $a = new Activity([
            'max_participants' => null,
            'is_host_passive' => false,
        ]);

        $this->assertNull($a->physicalHeadcountForSlotCapacity());
    }

    public function test_slot_fits_capacity_when_room_unlimited(): void
    {
        $activity = new Activity([
            'activity_type_id' => 1,
            'max_participants' => 99,
            'is_host_passive' => false,
        ]);

        $slot = new Slot(['max_capacity' => null]);

        $this->assertTrue($slot->fitsActivityCapacity($activity));
    }

    public function test_slot_fits_capacity_when_activity_headcount_unknown(): void
    {
        $activity = new Activity([
            'max_participants' => null,
            'is_host_passive' => false,
        ]);

        $slot = new Slot(['max_capacity' => 4]);

        $this->assertTrue($slot->fitsActivityCapacity($activity));
    }

    public function test_slot_rejects_when_physical_headcount_exceeds_capacity(): void
    {
        $activity = new Activity([
            'max_participants' => 5,
            'is_host_passive' => false,
        ]);

        $slot = new Slot(['max_capacity' => 5]);

        $this->assertFalse($slot->fitsActivityCapacity($activity));
    }

    public function test_slot_accepts_when_exactly_at_capacity_with_active_host(): void
    {
        $activity = new Activity([
            'max_participants' => 5,
            'is_host_passive' => false,
        ]);

        $slot = new Slot(['max_capacity' => 6]);

        $this->assertTrue($slot->fitsActivityCapacity($activity));
    }
}
