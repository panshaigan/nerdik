<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\ActivityLogoSource;
use App\Livewire\Events\EventShowPlanTab;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\User;
use App\Support\Ui\ListingCardPicture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\AttachesFixtureMedia;
use Tests\TestCase;

class ShowEventPlanTabCoverPicturesTest extends TestCase
{
    use AttachesFixtureMedia;
    use RefreshDatabase;

    public function test_plan_tab_exposes_cover_picture_for_activity_with_resolvable_media(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        $tag = Tag::factory()->create();
        $media = $this->attachTagSampleMedia($tag);

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'logo_source' => ActivityLogoSource::Tag,
            'tag_media_id' => $media->id,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(3),
        ]);

        $component = Livewire::withoutLazyLoading()
            ->test(EventShowPlanTab::class, ['eventId' => $event->id]);

        $component->assertViewHas('activityCoverPicturesById', function (array $pictures) use ($activity): bool {
            $picture = $pictures[(int) $activity->id] ?? null;

            return $picture instanceof ListingCardPicture
                && $picture->hasDisplayableImage();
        });
    }
}
