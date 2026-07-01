<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\Activities\Pages\EditActivity;
use App\Filament\Admin\Resources\Tags\Pages\EditTag;
use App\Models\Activity;
use App\Models\Place;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\User;
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
