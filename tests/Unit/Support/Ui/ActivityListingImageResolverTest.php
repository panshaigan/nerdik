<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ui;

use App\Actions\Seeders\AttachModelMediaFromPublic;
use App\Actions\Seeders\AttachTagMediaFromPublic;
use App\Enums\ActivityLogoSource;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Support\Media\MediaPictureSources;
use App\Support\Ui\ActivityListingImageResolver;
use App\Support\Ui\EventListingImageResolver;
use App\Support\Ui\ListingCardPicture;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ActivityListingImageResolverTest extends TestCase
{
    use RefreshDatabase;

    private ActivityListingImageResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ActivityTypeSeeder::class);
        $this->resolver = app(ActivityListingImageResolver::class);
    }

    #[Test]
    public function it_uses_user_selected_tag_media_first(): void
    {
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $gameTag = Tag::factory()->create(['tag_category_id' => $gameCategory->id]);
        $chosenTag = Tag::factory()->create(['tag_category_id' => $gameCategory->id]);

        $gameFixture = 'images/tag-game/resolver-game.jpg';
        $chosenFixture = 'images/tag-game/resolver-chosen.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($gameFixture));
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($chosenFixture));

        app(AttachTagMediaFromPublic::class)($gameTag, [$gameFixture]);
        app(AttachTagMediaFromPublic::class)($chosenTag, [$chosenFixture]);

        $chosenMedia = $chosenTag->refresh()->getFirstMedia('images');
        $this->assertNotNull($chosenMedia);

        $activity = Activity::factory()->create([
            'logo_source' => ActivityLogoSource::Tag,
            'tag_media_id' => $chosenMedia->id,
        ]);
        $activity->tags()->attach([$gameTag->id, $chosenTag->id]);

        $picture = $this->resolver->resolve($activity->load(Activity::listingCardEagerLoad()));

        $expected = MediaPictureSources::fromMediaWithPreset($chosenMedia, 'listing_card', $activity->name);

        $this->assertNotNull($picture->sources);
        $this->assertSame($expected->jpegSrc(), $picture->sources->jpegSrc());
    }

    #[Test]
    public function it_falls_back_to_game_tag_before_setting_and_genre(): void
    {
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $settingCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_SETTING]);
        $genreCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GENRE]);

        $gameTag = Tag::factory()->create(['tag_category_id' => $gameCategory->id]);
        $settingTag = Tag::factory()->create(['tag_category_id' => $settingCategory->id]);
        $genreTag = Tag::factory()->create(['tag_category_id' => $genreCategory->id]);

        $gameFixture = 'images/tag-game/resolver-game-only.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($gameFixture));
        app(AttachTagMediaFromPublic::class)($gameTag, [$gameFixture]);
        $gameMedia = $gameTag->refresh()->getFirstMedia('images');

        $activity = Activity::factory()->create(['logo_source' => null, 'tag_media_id' => null]);
        $activity->tags()->attach([$genreTag->id, $settingTag->id, $gameTag->id]);

        $picture = $this->resolver->resolve($activity->load(Activity::listingCardEagerLoad()));

        $expected = MediaPictureSources::fromMediaWithPreset($gameMedia, 'listing_card', $activity->name);

        $this->assertNotNull($picture->sources);
        $this->assertSame($expected->jpegSrc(), $picture->sources->jpegSrc());
    }

    #[Test]
    public function it_uses_uploaded_logo_path_when_logo_source_is_upload(): void
    {
        Storage::fake('public');
        $path = 'activity-logos/test-upload.jpg';
        Storage::disk('public')->put($path, UploadedFile::fake()->image('upload.jpg')->getContent());

        $activity = Activity::factory()->create([
            'logo_source' => ActivityLogoSource::Upload,
            'logo_path' => $path,
            'tag_media_id' => null,
        ]);

        $picture = $this->resolver->resolve($activity);

        $this->assertNull($picture->sources);
        $this->assertNotNull($picture->staticUrl);
        $this->assertStringContainsString($path, (string) $picture->staticUrl);
    }

    #[Test]
    public function it_uses_activity_type_media_before_global_fallback(): void
    {
        $activityType = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $this->assertNotNull($activityType);

        $fixture = 'images/listing/resolver-rpg-type.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($fixture));
        app(AttachModelMediaFromPublic::class)($activityType, [$fixture]);

        $activity = Activity::factory()->create([
            'activity_type_id' => $activityType->id,
            'logo_source' => null,
        ]);

        $picture = $this->resolver->resolve($activity->load(['activityType.media']));

        $this->assertNotNull($picture->sources);
    }

    #[Test]
    public function it_returns_global_fallback_when_nothing_else_matches(): void
    {
        $activity = Activity::factory()->create([
            'logo_source' => null,
            'tag_media_id' => null,
            'activity_type_id' => null,
        ]);

        $picture = $this->resolver->resolve($activity);

        $this->assertNull($picture->sources);
        $this->assertStringContainsString(
            ListingCardPicture::GLOBAL_FALLBACK_ASSET,
            (string) $picture->staticUrl,
        );
    }

    #[Test]
    public function it_excludes_event_listing_default_media_from_activity_type_fallback(): void
    {
        $activityType = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $this->assertNotNull($activityType);

        $eventFixture = 'images/listing/resolver-event-default.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($eventFixture));
        app(AttachModelMediaFromPublic::class)(
            $activityType,
            [$eventFixture],
            ['listing_role' => EventListingImageResolver::LISTING_ROLE],
        );

        $activity = Activity::factory()->create([
            'activity_type_id' => $activityType->id,
            'logo_source' => null,
        ]);

        $picture = $this->resolver->resolve($activity->load(['activityType.media']));

        $this->assertNull($picture->sources);
        $this->assertStringContainsString(
            ListingCardPicture::GLOBAL_FALLBACK_ASSET,
            (string) $picture->staticUrl,
        );
    }
}
