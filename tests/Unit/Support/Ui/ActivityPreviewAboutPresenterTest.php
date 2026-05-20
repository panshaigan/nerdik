<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ui;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use App\Support\Ui\ActivityPreviewAboutPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ActivityPreviewAboutPresenterTest extends TestCase
{
    use RefreshDatabase;

    private ActivityPreviewAboutPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = new ActivityPreviewAboutPresenter;
    }

    #[Test]
    public function build_uses_slot_time_and_venue_room_label_for_scheduled_activity(): void
    {
        $user = User::factory()->create();
        $venue = Place::factory()->venue()->create(['name' => 'Convention Center']);
        $room = Place::factory()->room($venue)->create(['name' => 'Hall A']);
        $event = Event::factory()->create(['created_by' => $user->id]);
        $startsAt = now()->addDay()->setTime(10, 0);
        $endsAt = (clone $startsAt)->setTime(12, 0);

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $slot = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'place_id' => $room->id,
            'name' => 'Table 3',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $activity->setRelation('slot', $slot->load('place.parent'));

        $about = $this->presenter->build($activity);

        $this->assertSame('Table 3', $about->slotName);
        $this->assertSame('Convention Center · Hall A', $about->locationLabel);
        $this->assertStringContainsString('10:00', $about->timeLabel);
        $this->assertStringContainsString('12:00', $about->timeLabel);
    }

    #[Test]
    public function build_uses_activity_schedule_for_self_hosted_activity(): void
    {
        $user = User::factory()->create();
        $venue = Place::factory()->venue()->create(['name' => 'Tavern Hall']);
        $startsAt = now()->addWeek()->setTime(14, 0);
        $endsAt = (clone $startsAt)->addHours(2);

        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $venue->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $activity->setRelation('place', $venue);

        $about = $this->presenter->build($activity);

        $this->assertNull($about->slotName);
        $this->assertSame('Tavern Hall', $about->locationLabel);
        $this->assertNotSame('', $about->timeLabel);
    }
}
