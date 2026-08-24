<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Media;

use App\Actions\Seeders\AttachTagMediaFromPublic;
use App\Models\Tag;
use App\Support\Media\MediaPictureSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MediaPictureSourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'media.test_profile' => 'full',
            'media.responsive_widths' => [128, 256, 384, 512, 768, 1024, 1536],
        ]);
    }

    #[Test]
    public function it_builds_srcset_strings_for_each_conversion(): void
    {
        $tag = Tag::factory()->create();

        $fixturePath = 'images/tag-game/fixture-picture.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($fixturePath));

        app(AttachTagMediaFromPublic::class)($tag, [$fixturePath]);

        $media = $tag->refresh()->getFirstMedia('images');
        $this->assertNotNull($media);

        $sources = MediaPictureSources::fromMediaWithPreset($media, 'tag_card', 'Test tag');

        $this->assertNotSame('', $sources->avifSrcset());
        $this->assertNotSame('', $sources->webpSrcset());
        $this->assertNotSame('', $sources->jpegSrcset());
        $this->assertNotSame('', $sources->jpegSrc());
        $this->assertSame('(max-width: 640px) 100vw, 384px', $sources->sizes());
        $this->assertSame('Test tag', $sources->alt());
    }

    #[Test]
    public function tag_hero_preset_allows_high_resolution_srcset_for_full_width_hero(): void
    {
        $tag = Tag::factory()->create();
        $fixturePath = 'images/tag-game/fixture-tag-hero.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($fixturePath));

        app(AttachTagMediaFromPublic::class)($tag, [$fixturePath]);

        $media = $tag->refresh()->getFirstMedia('images');
        $this->assertNotNull($media);

        $sources = MediaPictureSources::fromMediaWithPreset($media, 'tag_hero', 'Hero tag');
        $webpSrcset = $sources->webpSrcset();

        $this->assertSame(
            '(max-width: 1024px) calc(100vw - 3rem), calc(min(80rem, 100vw) - 4rem)',
            $sources->sizes(),
        );
        $this->assertStringContainsString('1024w', $webpSrcset);
        $this->assertGreaterThanOrEqual(
            1024,
            $this->largestSrcsetWidth($webpSrcset),
        );
    }

    #[Test]
    public function listing_card_preset_caps_srcset_widths(): void
    {
        config([
            'media.test_profile' => 'full',
            'media.responsive_widths' => [128, 256, 384, 512, 768, 1024, 1536],
        ]);

        $tag = Tag::factory()->create();
        $fixturePath = 'images/tag-game/fixture-srcset-cap.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($fixturePath));

        app(AttachTagMediaFromPublic::class)($tag, [$fixturePath]);

        $media = $tag->refresh()->getFirstMedia('images');
        $this->assertNotNull($media);

        $sources = MediaPictureSources::fromMediaWithPreset($media, 'listing_card', 'Cap test');
        $webpSrcset = $sources->webpSrcset();

        $this->assertStringContainsString('768w', $webpSrcset);
        $this->assertStringNotContainsString('1024w', $webpSrcset);
        $this->assertStringContainsString('512w', $webpSrcset);
        $this->assertSame(
            '(max-width: 767px) 100vw, (max-width: 1279px) 25vw, 286px',
            $sources->sizes(),
        );
    }

    #[Test]
    public function display_src_picks_smallest_fitting_responsive_candidate(): void
    {
        $tag = Tag::factory()->create();
        $fixturePath = 'images/tag-game/fixture-display-src.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($fixturePath));

        app(AttachTagMediaFromPublic::class)($tag, [$fixturePath]);

        $media = $tag->refresh()->getFirstMedia('images');
        $this->assertNotNull($media);

        $sources = MediaPictureSources::fromMedia(
            $media,
            sizes: '(max-width: 767px) 100vw, (max-width: 1279px) 25vw, 286px',
            maxSrcsetWidth: 512,
            displayWidth: 150,
        );

        $displayWidth = $this->widthForSrcsetUrl($sources->jpegSrcset(), $sources->displaySrc());
        $jpegWidth = $this->widthForSrcsetUrl($sources->jpegSrcset(), $sources->jpegSrc());

        $this->assertNotNull($displayWidth);
        $this->assertNotNull($jpegWidth);
        $this->assertLessThan($jpegWidth, $displayWidth);
        $this->assertSame(384, $displayWidth);
        $this->assertSame(512, $jpegWidth);
    }

    #[Test]
    public function media_picture_component_uses_display_src_for_img_fallback(): void
    {
        $tag = Tag::factory()->create();

        $fixturePath = 'images/tag-game/fixture-blade-display-src.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($fixturePath));

        app(AttachTagMediaFromPublic::class)($tag, [$fixturePath]);

        $media = $tag->refresh()->getFirstMedia('images');
        $sources = MediaPictureSources::fromMedia(
            $media,
            maxSrcsetWidth: 512,
            displayWidth: 150,
        );

        $html = view('components.media-picture', ['sources' => $sources])->render();

        $this->assertStringContainsString('src="'.$sources->displaySrc().'"', $html);
        $this->assertStringContainsString('responsive-images', $sources->displaySrc());
        $this->assertNotSame($sources->jpegSrc(), $sources->displaySrc());
    }

    #[Test]
    public function it_strips_spatie_tiny_placeholder_from_srcset(): void
    {
        config(['media-library.responsive_images.use_tiny_placeholders' => true]);

        $tag = Tag::factory()->create();
        $fixturePath = 'images/tag-game/fixture-no-placeholder.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($fixturePath));

        app(AttachTagMediaFromPublic::class)($tag, [$fixturePath]);

        $media = $tag->refresh()->getFirstMedia('images');
        $this->assertNotNull($media);

        $rawSrcset = $media->getSrcset('webp');
        $this->assertStringContainsString('data:image/svg+xml;base64,', $rawSrcset);

        $sources = MediaPictureSources::fromMediaWithPreset($media, 'listing_card', 'No placeholder');
        $webpSrcset = $sources->webpSrcset();

        $this->assertStringNotContainsString('data:image/svg+xml', $webpSrcset);
        $this->assertStringNotContainsString(' 32w', $webpSrcset);
        $this->assertStringContainsString('128w', $webpSrcset);
    }

    #[Test]
    public function media_picture_component_renders_picture_element(): void
    {
        $tag = Tag::factory()->create();

        $fixturePath = 'images/tag-game/fixture-blade.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($fixturePath));

        app(AttachTagMediaFromPublic::class)($tag, [$fixturePath]);

        $media = $tag->refresh()->getFirstMedia('images');
        $sources = MediaPictureSources::fromMedia($media);

        $html = view('components.media-picture', ['sources' => $sources])->render();

        $this->assertStringContainsString('<picture', $html);
        $this->assertStringContainsString('type="image/avif"', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('<img', $html);
    }

    private function largestSrcsetWidth(string $srcset): int
    {
        preg_match_all('/\s(\d+)w/', $srcset, $matches);

        if ($matches[1] === []) {
            return 0;
        }

        return max(array_map(intval(...), $matches[1]));
    }

    private function widthForSrcsetUrl(string $srcset, string $url): ?int
    {
        preg_match_all(
            '/((?:https?:\/\/|\/)?\S+)\s+(\d+w)/',
            $srcset,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            if ($match[1] === $url) {
                return (int) rtrim($match[2], 'w');
            }
        }

        return null;
    }
}
