<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\Tags\Pages\ListUnusedUserTags;
use App\Models\Activity;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UnusedUserTagsTableTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function table_shows_unused_tags_created_by_non_admin_users(): void
    {
        $admin = User::factory()->admin()->create();
        $regularUser = User::factory()->create();
        $organizer = User::factory()->organizer()->create();

        $visibleTag = Tag::factory()->create(['created_by' => $regularUser->id]);
        $organizerTag = Tag::factory()->create(['created_by' => $organizer->id]);
        $adminTag = Tag::factory()->create(['created_by' => $admin->id]);
        $systemTag = Tag::factory()->create(['created_by' => null]);
        $usedTag = Tag::factory()->create(['created_by' => $regularUser->id]);
        $activity = Activity::factory()->create();
        $activity->tags()->attach($usedTag->id);

        Livewire::actingAs($admin)
            ->test(ListUnusedUserTags::class)
            ->assertCanSeeTableRecords([$visibleTag, $organizerTag])
            ->assertCanNotSeeTableRecords([$adminTag, $systemTag, $usedTag]);
    }

    #[Test]
    public function table_hides_tag_after_it_is_attached_to_an_activity(): void
    {
        $admin = User::factory()->admin()->create();
        $regularUser = User::factory()->create();
        $tag = Tag::factory()->create(['created_by' => $regularUser->id]);

        Livewire::actingAs($admin)
            ->test(ListUnusedUserTags::class)
            ->assertCanSeeTableRecords([$tag]);

        $activity = Activity::factory()->create();
        $activity->tags()->attach($tag->id);

        Livewire::actingAs($admin)
            ->test(ListUnusedUserTags::class)
            ->assertCanNotSeeTableRecords([$tag]);
    }
}
