<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ActivityLogoSource;
use App\Enums\AvatarSource;
use App\Enums\EventLogoSource;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Tag;
use App\Models\User;
use App\Services\ContentModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AttachesFixtureMedia;
use Tests\TestCase;

final class ContentModerationServiceTest extends TestCase
{
    use AttachesFixtureMedia;
    use RefreshDatabase;

    #[Test]
    public function removing_activity_picture_clears_logo_source_and_uses_tag_fallback(): void
    {
        $activity = Activity::factory()->create([
            'logo_source' => ActivityLogoSource::Upload,
        ]);

        $this->attachTagSampleMedia($activity, collection: 'logo');

        $tag = Tag::factory()->create();
        $this->attachTagSampleMedia($tag);
        $activity->tags()->attach($tag->id);

        app(ContentModerationService::class)->removeActivityPicture($activity->fresh());

        $activity->refresh();

        $this->assertNull($activity->logo_source);
        $this->assertNull($activity->tag_media_id);
        $this->assertNull($activity->gallery_media_id);
        $this->assertNull($activity->getFirstMedia('logo'));
    }

    #[Test]
    public function updating_activity_name_preserves_slug(): void
    {
        $activity = Activity::factory()->create([
            'name' => 'Original title',
        ]);

        $originalSlug = $activity->slug;

        app(ContentModerationService::class)->updateActivityText(
            $activity,
            'Moderated title',
            'New description',
        );

        $activity->refresh();

        $this->assertSame('Moderated title', $activity->name);
        $this->assertSame('New description', $activity->description);
        $this->assertSame($originalSlug, $activity->slug);
    }

    #[Test]
    public function profile_placeholder_nickname_is_unique(): void
    {
        $user = User::factory()->create(['nickname' => 'offensive_name']);
        User::factory()->create(['nickname' => 'removed_'.$user->id]);

        app(ContentModerationService::class)->applyPlaceholderToProfile($user);

        $user->refresh();

        $this->assertSame('removed_'.$user->id.'_2', $user->nickname);
    }

    #[Test]
    public function removing_profile_picture_resets_avatar_source_to_generated(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'avatar_source' => AvatarSource::Uploaded,
        ]);

        $this->attachTagSampleMedia($user, collection: 'avatar');

        app(ContentModerationService::class)->removeProfilePicture($user->fresh(['profile']));

        $user->refresh();

        $this->assertSame(AvatarSource::Generated, $user->profile?->avatar_source);
        $this->assertNull($user->getFirstMedia('avatar'));
    }

    #[Test]
    public function removing_event_picture_clears_logo_source(): void
    {
        $event = Event::factory()->create([
            'logo_source' => EventLogoSource::Upload,
        ]);

        $this->attachTagSampleMedia($event, collection: 'logo');

        app(ContentModerationService::class)->removeEventPicture($event->fresh());

        $event->refresh();

        $this->assertNull($event->logo_source);
        $this->assertNull($event->listing_media_id);
        $this->assertNull($event->gallery_media_id);
    }
}
