<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagRelation;
use App\Models\TagTranslation;
use App\Models\User;
use App\Models\UserPlayArea;
use App\Services\UserPlayPreferencesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class UserPlayPreferencesServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_and_clear_play_area(): void
    {
        $user = User::factory()->create();
        $service = app(UserPlayPreferencesService::class);

        $area = $service->savePlayArea($user, 52.2297, 21.0122, 40, 'Warsaw');
        $this->assertSame($user->id, (int) $area->user_id);
        $this->assertSame('Warsaw', $area->label);

        $this->assertDatabaseHas('user_play_areas', [
            'user_id' => $user->id,
            'radius_km' => 40,
        ]);

        $service->clearPlayArea($user);

        $this->assertDatabaseMissing('user_play_areas', [
            'user_id' => $user->id,
        ]);
    }

    public function test_rejects_invalid_coordinates_and_radius(): void
    {
        $user = User::factory()->create();
        $service = app(UserPlayPreferencesService::class);

        $this->expectException(ValidationException::class);
        $service->savePlayArea($user, 95.0, 21.0, 25, null);
    }

    public function test_rejects_radius_out_of_range(): void
    {
        $user = User::factory()->create();
        $service = app(UserPlayPreferencesService::class);

        $this->expectException(ValidationException::class);
        $service->savePlayArea($user, 52.0, 21.0, 500, null);
    }

    public function test_sync_interest_tags_without_relation_expansion(): void
    {
        $user = User::factory()->create();
        $category = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $parent = Tag::factory()->for($category, 'tagCategory')->create();
        $child = Tag::factory()->for($category, 'tagCategory')->create();
        TagRelation::query()->create([
            'tag_id' => $child->id,
            'related_tag_id' => $parent->id,
        ]);

        app(UserPlayPreferencesService::class)->syncInterestTags($user, [$parent->id], []);

        $user->refresh();
        $ids = $user->interestTags->pluck('id')->all();

        $this->assertSame([$parent->id], $ids);
    }

    public function test_sync_reuses_existing_tag_by_label(): void
    {
        $user = User::factory()->create();
        $category = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $existing = Tag::factory()->for($category, 'tagCategory')->create();
        TagTranslation::query()->create([
            'tag_id' => $existing->id,
            'locale' => 'en',
            'label' => 'Catan',
        ]);

        $beforeCount = Tag::query()->count();

        app(UserPlayPreferencesService::class)->syncInterestTags($user, [], [
            ['label' => 'catan', 'category_id' => $category->id],
        ]);

        $this->assertSame($beforeCount, Tag::query()->count());
        $user->refresh();
        $this->assertSame([(int) $existing->id], $user->interestTags->pluck('id')->all());
    }

    public function test_play_area_updates_in_place_for_user(): void
    {
        $user = User::factory()->create();
        $service = app(UserPlayPreferencesService::class);

        $first = $service->savePlayArea($user, 50.0, 20.0, 25, 'Krakow');
        $second = $service->savePlayArea($user, 52.0, 21.0, 30, 'Warsaw');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, UserPlayArea::query()->where('user_id', $user->id)->count());
        $this->assertSame('Warsaw', $second->label);
    }
}
