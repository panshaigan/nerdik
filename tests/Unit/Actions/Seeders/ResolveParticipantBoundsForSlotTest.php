<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Seeders;

use App\Actions\Seeders\ResolveParticipantBoundsForSlot;
use App\Models\Activity;
use App\Models\Slot;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ResolveParticipantBoundsForSlotTest extends TestCase
{
    private ResolveParticipantBoundsForSlot $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ResolveParticipantBoundsForSlot;
    }

    #[Test]
    public function it_caps_max_participants_for_active_host_against_slot_capacity(): void
    {
        $slot = new Slot(['max_capacity' => 6]);
        $activity = new Activity([
            'max_participants' => 10,
            'is_host_passive' => false,
        ]);

        $bounds = ($this->action)($slot, $activity);

        $activity->fill($bounds);

        $this->assertSame(5, $bounds['max_participants']);
        $this->assertGreaterThanOrEqual(1, $bounds['min_participants']);
        $this->assertLessThanOrEqual($bounds['max_participants'], $bounds['min_participants']);
        $this->assertTrue($slot->fitsActivityCapacity($activity));
    }

    #[Test]
    public function it_allows_full_slot_capacity_for_passive_host(): void
    {
        $slot = new Slot(['max_capacity' => 6]);
        $activity = new Activity([
            'max_participants' => 10,
            'is_host_passive' => true,
        ]);

        $bounds = ($this->action)($slot, $activity);

        $activity->fill($bounds);

        $this->assertSame(6, $bounds['max_participants']);
        $this->assertTrue($slot->fitsActivityCapacity($activity));
    }

    #[Test]
    public function it_handles_minimum_slot_capacity_with_active_host(): void
    {
        $slot = new Slot(['max_capacity' => 1]);
        $activity = new Activity([
            'is_host_passive' => false,
        ]);

        $bounds = ($this->action)($slot, $activity);

        $activity->fill($bounds);

        $this->assertSame(0, $bounds['max_participants']);
        $this->assertSame(0, $bounds['min_participants']);
        $this->assertTrue($slot->fitsActivityCapacity($activity));
    }

    #[Test]
    public function it_returns_reasonable_bounds_when_slot_capacity_is_unlimited(): void
    {
        $slot = new Slot(['max_capacity' => null]);
        $activity = new Activity([
            'is_host_passive' => false,
        ]);

        $bounds = ($this->action)($slot, $activity);

        $this->assertGreaterThanOrEqual(1, $bounds['min_participants']);
        $this->assertLessThanOrEqual(3, $bounds['min_participants']);
        $this->assertGreaterThanOrEqual(3, $bounds['max_participants']);
        $this->assertLessThanOrEqual(8, $bounds['max_participants']);
        $this->assertLessThanOrEqual($bounds['max_participants'], $bounds['min_participants']);
    }
}
