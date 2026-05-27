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
}
