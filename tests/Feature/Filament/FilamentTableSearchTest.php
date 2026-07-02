<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Filament\Admin\Resources\TagRelations\Pages\ListTagRelations;
use App\Filament\Admin\Resources\Tags\Pages\ListTags;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Place;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagRelation;
use App\Models\User;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FilamentTableSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ActivityTypeSeeder::class);
    }

    #[Test]
    public function tags_table_search_finds_tag_category_by_translated_label_without_sql_error(): void
    {
        $admin = User::factory()->admin()->create();
        $category = TagCategory::factory()->create(['key' => 'asia-games']);
        $category->translations()->create([
            'locale' => 'en',
            'label' => 'Asia',
        ]);
        $matchingTag = Tag::factory()->create(['tag_category_id' => $category->id]);
        $otherTag = Tag::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListTags::class)
            ->searchTable('asia')
            ->assertCanSeeTableRecords([$matchingTag])
            ->assertCanNotSeeTableRecords([$otherTag]);
    }

    #[Test]
    public function tags_table_search_finds_tag_by_english_label(): void
    {
        $admin = User::factory()->admin()->create();
        $matchingTag = Tag::factory()->create();
        $matchingTag->translations()->create([
            'locale' => 'en',
            'label' => 'Death',
            'slug' => 'death',
        ]);
        $otherTag = Tag::factory()->create();
        $otherTag->translations()->create([
            'locale' => 'en',
            'label' => 'Life',
            'slug' => 'life',
        ]);

        Livewire::actingAs($admin)
            ->test(ListTags::class)
            ->searchTable('Death')
            ->assertCanSeeTableRecords([$matchingTag])
            ->assertCanNotSeeTableRecords([$otherTag]);
    }

    #[Test]
    public function tags_table_search_finds_tag_by_polish_label(): void
    {
        $admin = User::factory()->admin()->create();
        $matchingTag = Tag::factory()->create();
        $matchingTag->translations()->create([
            'locale' => 'pl',
            'label' => 'Śmierć',
            'slug' => 'smierc',
        ]);
        $otherTag = Tag::factory()->create();
        $otherTag->translations()->create([
            'locale' => 'pl',
            'label' => 'Życie',
            'slug' => 'zycie',
        ]);

        Livewire::actingAs($admin)
            ->test(ListTags::class)
            ->searchTable('Śmierć')
            ->assertCanSeeTableRecords([$matchingTag])
            ->assertCanNotSeeTableRecords([$otherTag]);
    }

    #[Test]
    public function tags_table_search_finds_tag_by_locale_alias(): void
    {
        $admin = User::factory()->admin()->create();
        $matchingTag = Tag::factory()->create();
        $matchingTag->aliases()->create([
            'locale' => 'en',
            'alias' => 'grim-reaper',
        ]);
        $otherTag = Tag::factory()->create();
        $otherTag->translations()->create([
            'locale' => 'en',
            'label' => 'Unrelated',
            'slug' => 'unrelated',
        ]);

        Livewire::actingAs($admin)
            ->test(ListTags::class)
            ->searchTable('grim-reaper')
            ->assertCanSeeTableRecords([$matchingTag])
            ->assertCanNotSeeTableRecords([$otherTag]);
    }

    #[Test]
    public function activities_table_search_finds_activity_by_activity_type_slug(): void
    {
        $admin = User::factory()->admin()->create();
        $rpgType = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $boardType = ActivityType::findBySlug(ActivityType::SLUG_BOARD);
        $matchingActivity = Activity::factory()->create(['activity_type_id' => $rpgType?->id]);
        $otherActivity = Activity::factory()->create(['activity_type_id' => $boardType?->id]);

        Livewire::actingAs($admin)
            ->test(ListActivities::class)
            ->searchTable('rpg')
            ->assertCanSeeTableRecords([$matchingActivity])
            ->assertCanNotSeeTableRecords([$otherActivity]);
    }

    #[Test]
    public function activities_table_search_finds_activity_by_parent_venue_name(): void
    {
        $admin = User::factory()->admin()->create();
        $venue = Place::factory()->venue()->create(['name' => 'Grand Convention Hall']);
        $room = Place::factory()->room($venue)->create(['name' => 'Room A']);
        $matchingActivity = Activity::factory()->create(['place_id' => $room->id]);
        $otherActivity = Activity::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListActivities::class)
            ->searchTable('Grand Convention')
            ->assertCanSeeTableRecords([$matchingActivity])
            ->assertCanNotSeeTableRecords([$otherActivity]);
    }

    #[Test]
    public function activities_table_filter_by_activity_type_does_not_use_invalid_column_name(): void
    {
        $admin = User::factory()->admin()->create();
        $rpgType = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $boardType = ActivityType::findBySlug(ActivityType::SLUG_BOARD);

        $matchingActivity = Activity::factory()->create(['activity_type_id' => $rpgType?->id]);
        $otherActivity = Activity::factory()->create(['activity_type_id' => $boardType?->id]);

        Livewire::actingAs($admin)
            ->test(ListActivities::class)
            ->filterTable('activityType', $rpgType?->id)
            ->assertCanSeeTableRecords([$matchingActivity])
            ->assertCanNotSeeTableRecords([$otherActivity]);
    }

    #[Test]
    public function activities_table_sort_by_activity_type_does_not_use_invalid_column_name(): void
    {
        $admin = User::factory()->admin()->create();
        $rpgType = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $boardType = ActivityType::findBySlug(ActivityType::SLUG_BOARD);

        $boardActivity = Activity::factory()->create(['activity_type_id' => $boardType?->id]);
        $rpgActivity = Activity::factory()->create(['activity_type_id' => $rpgType?->id]);

        Livewire::actingAs($admin)
            ->test(ListActivities::class)
            ->sortTable('activityType', 'asc')
            ->assertCanSeeTableRecords([$boardActivity, $rpgActivity], inOrder: true);
    }

    #[Test]
    public function tag_relations_table_search_finds_relation_by_related_tag_label(): void
    {
        $admin = User::factory()->admin()->create();
        $relatedTag = Tag::factory()->create();
        $relatedTag->translations()->create([
            'locale' => 'en',
            'label' => 'Companion Tag',
            'slug' => 'companion-tag',
        ]);
        $matchingRelation = TagRelation::query()->create([
            'tag_id' => Tag::factory()->create()->id,
            'related_tag_id' => $relatedTag->id,
        ]);
        $otherRelation = TagRelation::query()->create([
            'tag_id' => Tag::factory()->create()->id,
            'related_tag_id' => Tag::factory()->create()->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ListTagRelations::class)
            ->searchTable('Companion')
            ->assertCanSeeTableRecords([$matchingRelation])
            ->assertCanNotSeeTableRecords([$otherRelation]);
    }
}
