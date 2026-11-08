<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Livewire\Activities\UserContactPopover;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use App\Models\User;
use App\Models\UserPlayArea;
use App\Services\UserPlayPreferencesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

final class ProfilePlayPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_form_persists_play_area_and_tags(): void
    {
        $user = User::factory()->create();
        $category = TagCategory::factory()->create(['key' => TagCategory::KEY_MECHANIC]);
        $tag = Tag::factory()->for($category, 'tagCategory')->create();
        TagTranslation::query()->create([
            'tag_id' => $tag->id,
            'locale' => 'en',
            'label' => 'Deck building',
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-availability-form')
            ->set('playLatitude', 52.2297)
            ->set('playLongitude', 21.0122)
            ->set('playRadiusKm', 35)
            ->set('playLabel', 'Warsaw')
            ->set('tag_ids', [(int) $tag->id]);

        $user->refresh();
        $user->load(['playArea', 'interestTags']);

        $this->assertNotNull($user->playArea);
        $this->assertSame(35, (int) $user->playArea->radius_km);
        $this->assertSame('Warsaw', $user->playArea->label);
        $this->assertSame([(int) $tag->id], $user->interestTags->pluck('id')->all());
    }

    public function test_user_contact_popover_exposes_play_preferences_data(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create();
        $category = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $tag = Tag::factory()->for($category, 'tagCategory')->create();
        TagTranslation::query()->create([
            'tag_id' => $tag->id,
            'locale' => 'en',
            'label' => 'Azul',
        ]);

        UserPlayArea::query()->create([
            'user_id' => $target->id,
            'latitude' => 52.2297,
            'longitude' => 21.0122,
            'radius_km' => 25,
            'label' => 'Warsaw',
        ]);
        $target->interestTags()->sync([$tag->id]);

        Livewire::actingAs($viewer)
            ->test(UserContactPopover::class, ['targetUserId' => $target->id])
            ->assertSee('Warsaw', false)
            ->assertSee('Azul', false)
            ->assertSeeHtml('data-ui="user-contact-popover-tab-play-preferences"');
    }

    public function test_clear_play_area_from_form(): void
    {
        $user = User::factory()->create();
        app(UserPlayPreferencesService::class)->savePlayArea($user, 52.0, 21.0, 25, 'Warsaw');

        $this->actingAs($user);

        Volt::test('profile.update-availability-form')
            ->call('clearPlayArea')
            ->assertSet('playLatitude', null)
            ->assertSet('playLongitude', null);

        $this->assertDatabaseMissing('user_play_areas', ['user_id' => $user->id]);
    }
}
