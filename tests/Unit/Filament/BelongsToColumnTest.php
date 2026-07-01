<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Filament\Tables\Columns\BelongsToColumn;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\TagCategory;
use App\Models\TagContext;
use App\Models\User;
use App\Support\Filament\FilamentRecordLabel;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BelongsToColumnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ActivityTypeSeeder::class);
    }

    #[Test]
    public function user_column_maps_created_by_to_creator_relationship(): void
    {
        $column = BelongsToColumn::user('created_by');

        $this->assertSame('creator', $column->getName());
    }

    #[Test]
    public function user_column_resolves_creator_display_name(): void
    {
        $user = User::factory()->create(['nickname' => 'admin_handle']);

        $this->assertSame('admin_handle', FilamentRecordLabel::for($user));
    }

    #[Test]
    public function place_column_resolves_room_with_venue_prefix(): void
    {
        $venue = Place::factory()->venue()->create(['name' => 'Grand Hall']);
        $room = Place::factory()->room($venue)->create(['name' => 'Room A']);

        $this->assertSame('Grand Hall · Room A', FilamentRecordLabel::for($room));
    }

    #[Test]
    public function place_name_column_uses_venue_room_label(): void
    {
        $venue = Place::factory()->venue()->create(['name' => 'Grand Hall']);
        $room = Place::factory()->room($venue)->create(['name' => 'Room A']);

        $this->assertSame('Grand Hall · Room A', $room->venueRoomLabel());
    }

    #[Test]
    public function slot_column_resolves_event_prefixed_name(): void
    {
        $event = Event::factory()->create(['name' => 'My Event']);
        $slot = Slot::factory()->create([
            'event_id' => $event->id,
            'name' => 'Table 1',
        ]);

        $this->assertSame('My Event — Table 1', FilamentRecordLabel::slot($slot));
    }

    #[Test]
    public function record_column_resolves_tag_category_label(): void
    {
        $category = TagCategory::factory()->create(['key' => 'game']);
        $category->translations()->create([
            'locale' => 'en',
            'label' => 'Game',
        ]);

        $this->assertSame('Game', FilamentRecordLabel::for($category));
    }

    #[Test]
    public function slot_column_uses_slot_relationship_name(): void
    {
        $column = BelongsToColumn::slot('acceptedSlot');

        $this->assertSame('acceptedSlot', $column->getName());
    }

    #[Test]
    public function morph_context_column_falls_back_when_context_is_missing(): void
    {
        $tagContext = TagContext::factory()->create([
            'context_type' => TagContext::CONTEXT_TYPE_ACTIVITY_TYPE,
            'context_id' => 999_999,
        ]);

        $this->assertSame(
            TagContext::CONTEXT_TYPE_ACTIVITY_TYPE.' #999999',
            "{$tagContext->context_type} #{$tagContext->context_id}",
        );
    }
}
