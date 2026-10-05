<?php

namespace Tests\Unit\Factories;

use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventEnrollmentWindowFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_consistent_with_event_copies_created_by_from_event(): void
    {
        $creator = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $creator->id]);

        $window = EventEnrollmentWindow::factory()
            ->for($event)
            ->consistentWithEvent()
            ->create();

        $this->assertSame($creator->id, $window->created_by);
    }
}
