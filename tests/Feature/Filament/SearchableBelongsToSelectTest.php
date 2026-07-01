<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\Activities\Pages\EditActivity;
use App\Filament\Admin\Resources\ActivityTypeSlots\Pages\EditActivityTypeSlot;
use App\Filament\Admin\Resources\Tags\Pages\EditTag;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\ActivityTypeSlot;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\User;
use App\Support\Filament\FilamentRecordLabel;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SearchableBelongsToSelectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ActivityTypeSeeder::class);
    }

    #[Test]
    public function activity_edit_form_preloads_place_options_with_names_on_open(): void
    {
        $admin = User::factory()->admin()->create();
        $places = Place::factory()->venue()->count(3)->sequence(
            ['name' => 'Alpha Venue'],
            ['name' => 'Beta Venue'],
            ['name' => 'Gamma Venue'],
        )->create();
        $activity = Activity::factory()->create(['place_id' => $places->first()->id]);

        Livewire::actingAs($admin)
            ->test(EditActivity::class, ['record' => $activity->slug])
            ->assertOk()
            ->call('callSchemaComponentMethod', 'form.place_id', 'getOptionsForJs')
            ->assertReturned(fn (array $options): bool => collect($options)->contains(
                fn (array $option): bool => $option['label'] === 'Alpha Venue' && $option['value'] === (string) $places->first()->id,
            ));
    }

    #[Test]
    public function activity_edit_form_displays_related_place_name_instead_of_id(): void
    {
        $admin = User::factory()->admin()->create();
        $place = Place::factory()->venue()->create(['name' => 'Filament Test Place Label']);
        $activity = Activity::factory()->create(['place_id' => $place->id]);

        Livewire::actingAs($admin)
            ->test(EditActivity::class, ['record' => $activity->slug])
            ->assertOk()
            ->assertSee('Filament Test Place Label');
    }

    #[Test]
    public function activity_edit_form_displays_creator_nickname_for_audit_field(): void
    {
        $admin = User::factory()->admin()->create();
        $creator = User::factory()->create(['nickname' => 'filament_creator_nick']);
        $activity = Activity::factory()->create(['created_by' => $creator->id]);

        Livewire::actingAs($admin)
            ->test(EditActivity::class, ['record' => $activity->slug])
            ->assertOk()
            ->assertSee('filament_creator_nick');
    }

    #[Test]
    public function activity_edit_form_displays_room_place_with_venue_prefix(): void
    {
        $admin = User::factory()->admin()->create();
        $venue = Place::factory()->venue()->create(['name' => 'Convention Center']);
        $room = Place::factory()->room($venue)->create(['name' => 'Hall A']);
        $activity = Activity::factory()->create(['place_id' => $room->id]);

        Livewire::actingAs($admin)
            ->test(EditActivity::class, ['record' => $activity->slug])
            ->assertOk()
            ->assertSee('Convention Center · Hall A');
    }

    #[Test]
    public function activity_edit_form_displays_cancelled_event_with_date_in_label(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create([
            'name' => 'Cancelled Filament Event',
            'starts_at' => '2026-09-10 12:00:00',
        ]);
        $activity = Activity::factory()->create([
            'cancelled_with_event_id' => $event->id,
        ]);

        Livewire::actingAs($admin)
            ->test(EditActivity::class, ['record' => $activity->slug])
            ->assertOk()
            ->assertSee('Cancelled Filament Event')
            ->assertSee('2026-09-10');
    }

    #[Test]
    public function activity_type_slot_form_displays_event_name_in_slot_label(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['name' => 'Filament Event Label']);
        $slot = Slot::factory()->create([
            'event_id' => $event->id,
            'name' => 'Morning Slot',
        ]);
        $activityTypeSlot = ActivityTypeSlot::query()->create([
            'slot_id' => $slot->id,
            'activity_type_id' => ActivityType::findBySlug(ActivityType::SLUG_RPG)->id,
        ]);

        Livewire::actingAs($admin)
            ->test(EditActivityTypeSlot::class, ['record' => $activityTypeSlot->id])
            ->assertOk()
            ->assertSee('Filament Event Label — Morning Slot');
    }

    #[Test]
    public function slot_select_finds_slot_by_event_name(): void
    {
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create(['name' => 'Unique Slot Event Search']);
        $slot = Slot::factory()->create([
            'event_id' => $event->id,
            'name' => 'Afternoon Table',
        ]);
        $activityTypeSlot = ActivityTypeSlot::query()->create([
            'slot_id' => $slot->id,
            'activity_type_id' => ActivityType::findBySlug(ActivityType::SLUG_RPG)->id,
        ]);

        Livewire::actingAs($admin)
            ->test(EditActivityTypeSlot::class, ['record' => $activityTypeSlot->id])
            ->assertOk()
            ->call('callSchemaComponentMethod', 'form.slot_id', 'getSearchResultsForJs', ['Unique Slot Event Search'])
            ->assertReturned(fn (array $options): bool => collect($options)->contains(
                fn (array $option): bool => $option['label'] === FilamentRecordLabel::for($slot)
                    && $option['value'] === (string) $slot->id,
            ));
    }

    #[Test]
    public function activity_edit_form_finds_room_by_venue_name_when_searching_place_field(): void
    {
        $admin = User::factory()->admin()->create();
        $venue = Place::factory()->venue()->create(['name' => 'Unique Venue Search Name']);
        $room = Place::factory()->room($venue)->create(['name' => 'Hidden Room Name']);
        $activity = Activity::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditActivity::class, ['record' => $activity->slug])
            ->assertOk()
            ->call('callSchemaComponentMethod', 'form.place_id', 'getSearchResultsForJs', ['Unique Venue Search Name'])
            ->assertReturned(fn (array $options): bool => collect($options)->contains(
                fn (array $option): bool => $option['label'] === 'Unique Venue Search Name · Hidden Room Name'
                    && $option['value'] === (string) $room->id,
            ));
    }

    #[Test]
    public function tag_edit_form_displays_tag_category_label(): void
    {
        $admin = User::factory()->admin()->create();
        $category = TagCategory::factory()->create(['key' => 'genre']);
        $category->translations()->create([
            'locale' => 'en',
            'label' => 'Genre Category Label',
        ]);
        $tag = Tag::factory()->create(['tag_category_id' => $category->id]);

        Livewire::actingAs($admin)
            ->test(EditTag::class, ['record' => $tag->id])
            ->assertOk()
            ->assertSee('Genre Category Label');
    }
}
