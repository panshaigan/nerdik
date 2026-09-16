<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HasAutoSlugTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function make_unique_slug_skips_soft_deleted_slugs(): void
    {
        $user = User::factory()->create();
        $deleted = Event::factory()->create([
            'created_by' => $user->id,
            'name' => 'Porzucane III',
            'slug' => 'porzucane-iii',
        ]);
        $deleted->delete();

        $this->assertNotNull($deleted->fresh()->deleted_at);
        $this->assertNull(Event::query()->where('slug', 'porzucane-iii')->first());

        $slug = Event::makeUniqueSlug('Porzucane III');

        $this->assertSame('porzucane-iii-2', $slug);
    }

    #[Test]
    public function creating_event_with_name_matching_trashed_slug_succeeds(): void
    {
        $user = User::factory()->organizer()->create();
        $deleted = Event::factory()->create([
            'created_by' => $user->id,
            'name' => 'Porzucane III',
            'slug' => 'porzucane-iii',
        ]);
        $deleted->delete();

        $event = Event::create([
            'name' => 'Porzucane III',
            'is_public' => true,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(4),
            'created_by' => $user->id,
        ]);

        $this->assertSame('porzucane-iii-2', $event->slug);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'slug' => 'porzucane-iii-2',
        ]);
    }
}
