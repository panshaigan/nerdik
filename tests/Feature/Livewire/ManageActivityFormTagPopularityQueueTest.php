<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Jobs\RecalculateTagPopularityJob;
use App\Livewire\Activities\ManageActivityForm;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ManageActivityFormTagPopularityQueueTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_recalculation_for_selected_tags_on_create(): void
    {
        Queue::fake();
        $this->seed(ActivityTypeSeeder::class);

        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('name', 'Queue Create Activity')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('tag_ids', [(int) $tag->id])
            ->call('save')
            ->assertHasNoErrors();

        Queue::assertPushed(RecalculateTagPopularityJob::class, function (RecalculateTagPopularityJob $job) use ($tag): bool {
            return $job->tagIds === [(int) $tag->id];
        });
    }

    #[Test]
    public function it_dispatches_recalculation_for_old_and_new_tags_on_edit_change(): void
    {
        Queue::fake();
        $this->seed(ActivityTypeSeeder::class);

        $user = User::factory()->create();
        $oldTag = Tag::factory()->create();
        $newTag = Tag::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $activity->tags()->sync([$oldTag->id]);

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class, ['activity' => $activity])
            ->set('name', 'Queue Edit Activity')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('tag_ids', [(int) $newTag->id])
            ->call('save')
            ->assertHasNoErrors();

        Queue::assertPushed(RecalculateTagPopularityJob::class, function (RecalculateTagPopularityJob $job) use ($oldTag, $newTag): bool {
            $ids = $job->tagIds;
            sort($ids);
            $expectedIds = [(int) $oldTag->id, (int) $newTag->id];
            sort($expectedIds);

            return $ids === $expectedIds;
        });
    }

    #[Test]
    public function it_does_not_dispatch_recalculation_when_tag_selection_does_not_change(): void
    {
        Queue::fake();
        $this->seed(ActivityTypeSeeder::class);

        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $activity->tags()->sync([$tag->id]);

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class, ['activity' => $activity])
            ->set('name', 'Queue No Change Activity')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('tag_ids', [(int) $tag->id])
            ->call('save')
            ->assertHasNoErrors();

        Queue::assertNotPushed(RecalculateTagPopularityJob::class);
    }
}
