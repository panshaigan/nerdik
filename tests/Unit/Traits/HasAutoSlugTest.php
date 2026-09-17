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
    public function make_unique_slug_reuses_soft_deleted_slugs(): void
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

        $this->assertSame('porzucane-iii', $slug);
    }

    #[Test]
    public function creating_event_with_name_matching_trashed_slug_reclaims_slug(): void
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

        $this->assertSame('porzucane-iii', $event->slug);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'slug' => 'porzucane-iii',
        ]);
    }

    #[Test]
    public function restoring_keeps_slug_when_still_available(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $user->id,
            'name' => 'Porzucane III',
            'slug' => 'porzucane-iii',
        ]);
        $event->delete();

        $event->restore();

        $this->assertSame('porzucane-iii', $event->fresh()->slug);
        $this->assertNull($event->fresh()->deleted_at);
    }

    #[Test]
    public function restoring_re_uniquifies_slug_when_live_row_took_it(): void
    {
        $user = User::factory()->create();
        $trashed = Event::factory()->create([
            'created_by' => $user->id,
            'name' => 'Porzucane III',
            'slug' => 'porzucane-iii',
        ]);
        $trashed->delete();

        $live = Event::factory()->create([
            'created_by' => $user->id,
            'name' => 'Porzucane III',
            'slug' => 'porzucane-iii',
        ]);

        $this->assertSame('porzucane-iii', $live->slug);

        $trashed->restore();

        $this->assertSame('porzucane-iii', $live->fresh()->slug);
        $this->assertSame('porzucane-iii-2', $trashed->fresh()->slug);
        $this->assertNull($trashed->fresh()->deleted_at);
    }
}
