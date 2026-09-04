<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Actions\Seeders\AttachModelMediaFromPublic;
use App\Enums\ActivityLogoSource;
use App\Models\Activity;
use App\Models\Tag;
use App\View\Components\Cards\ListingCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AttachesFixtureMedia;
use Tests\TestCase;

final class ListingCardPictureTest extends TestCase
{
    use AttachesFixtureMedia;
    use RefreshDatabase;

    #[Test]
    public function listing_card_renders_responsive_picture_for_activity_cover(): void
    {
        $tag = Tag::factory()->create();
        $media = $this->attachTagSampleMedia($tag, 'tests/fixtures/listing-card-feature.jpg');

        $activity = Activity::factory()->create([
            'logo_source' => ActivityLogoSource::Tag,
            'tag_media_id' => $media->id,
        ]);
        $activity->setRelation('tagMedia', $media);

        $component = new ListingCard($activity);
        $html = $component->render()->with($component->data())->render();

        $this->assertStringContainsString('<picture', $html);
        $this->assertStringContainsString('rounded-2xl', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('25vw', $html);
        $this->assertStringContainsString('286px', $html);
        $this->assertStringNotContainsString('768w', $html);
        $this->assertStringNotContainsString('320px', $html);
    }

    #[Test]
    public function listing_card_media_fills_aspect_video_box_for_non_sixteen_by_nine_cover(): void
    {
        $tag = Tag::factory()->create();
        $sourcePath = database_path('seeders/tag_images/Settings/70_warhammer_fantasy.jpg');

        if (! is_file($sourcePath)) {
            $this->markTestSkipped('Warhammer Fantasy seed image is not available.');
        }

        app(AttachModelMediaFromPublic::class)->attachFile(
            $tag,
            $sourcePath,
            'seeders/tag_images/Settings/70_warhammer_fantasy.jpg',
        );
        $media = $tag->refresh()->getFirstMedia('images');
        $this->assertNotNull($media);

        $activity = Activity::factory()->create([
            'logo_source' => ActivityLogoSource::Tag,
            'tag_media_id' => $media->id,
        ]);
        $activity->setRelation('tagMedia', $media);

        $component = new ListingCard($activity);
        $html = $component->render()->with($component->data())->render();

        $this->assertStringContainsString('ui-listing-card__media', $html);
        $this->assertStringContainsString('overflow-hidden', $html);
        $this->assertStringContainsString('rounded-2xl', $html);
        $this->assertStringContainsString('absolute inset-0', $html);
        $this->assertStringContainsString('size-full', $html);
        $this->assertStringContainsString('object-cover', $html);
    }
}
