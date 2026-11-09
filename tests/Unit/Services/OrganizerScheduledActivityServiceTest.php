<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use App\Services\OrganizerScheduledActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganizerScheduledActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function rpgActivityType(): ActivityType
    {
        return ActivityType::query()->where('slug', ActivityType::SLUG_RPG)->first()
            ?? ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
    }

    #[Test]
    public function create_persists_activity_slot_and_links_type(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $startsAt = $event->starts_at->copy()->addHour()->format('Y-m-d\TH:i');
        $endsAt = $event->starts_at->copy()->addHours(3)->format('Y-m-d\TH:i');
        $rpgId = $this->rpgActivityType()->id;

        $result = app(OrganizerScheduledActivityService::class)->create($event, $user, [
            'name' => 'Service Created',
            'activity_type_id' => $rpgId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'participation_mode' => 'open',
            'allows_observers' => false,
            'collect_familiarity' => false,
        ]);

        $this->assertSame('Service Created', $result['activity']->name);
        $this->assertSame(Activity::HOSTING_MODE_SCHEDULED_ON_EVENT, (int) $result['activity']->hosting_mode);
        $this->assertSame($result['activity']->id, $result['slot']->activity_id);
        $this->assertSame([$rpgId], $result['slot']->activity_types_ids);
    }

    #[Test]
    public function create_requires_venue_when_event_lists_places(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $user->id]);
        $venue = Place::create(['name' => 'Hall', 'type' => 'venue', 'is_online' => false]);
        $event->places()->attach($venue->id);

        $this->expectException(ValidationException::class);

        app(OrganizerScheduledActivityService::class)->create($event, $user, [
            'name' => 'No venue',
            'activity_type_id' => $this->rpgActivityType()->id,
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'participation_mode' => 'open',
        ]);
    }
}
