<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PlacePlanLabelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function single_venue_event_shows_room_name_only(): void
    {
        $venue = Place::factory()->venue()->create(['name' => 'Grand Hall']);
        $room = Place::factory()->room($venue)->create(['name' => 'Room A']);

        $this->assertSame('Room A', $room->planLabel(includeVenue: false));
    }

    #[Test]
    public function single_venue_event_hides_venue_only_place(): void
    {
        $venue = Place::factory()->venue()->create(['name' => 'Grand Hall']);

        $this->assertNull($venue->planLabel(includeVenue: false));
    }

    #[Test]
    public function multi_venue_event_shows_venue_and_room(): void
    {
        $venue = Place::factory()->venue()->create(['name' => 'Grand Hall']);
        $room = Place::factory()->room($venue)->create(['name' => 'Room A']);

        $this->assertSame('Grand Hall · Room A', $room->planLabel(includeVenue: true));
    }

    #[Test]
    public function multi_venue_event_shows_venue_when_no_room(): void
    {
        $venue = Place::factory()->venue()->create(['name' => 'Grand Hall']);

        $this->assertSame('Grand Hall', $venue->planLabel(includeVenue: true));
    }
}
