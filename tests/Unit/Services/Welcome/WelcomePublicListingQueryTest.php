<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Welcome;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use App\Services\Welcome\WelcomePublicListingQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WelcomePublicListingQueryTest extends TestCase
{
    use RefreshDatabase;

    private WelcomePublicListingQuery $query;

    protected function setUp(): void
    {
        parent::setUp();

        $this->query = app(WelcomePublicListingQuery::class);
    }

    #[Test]
    public function it_counts_upcoming_and_ongoing_public_listings_separately(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create();

        Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(3),
        ]);

        Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(3),
        ]);

        Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'starts_at' => now()->subMinutes(30),
            'ends_at' => now()->addMinutes(90),
        ]);

        Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(2),
        ]);

        $this->assertSame(2, $this->query->upcomingCount());
        $this->assertSame(2, $this->query->ongoingCount());
    }
}
