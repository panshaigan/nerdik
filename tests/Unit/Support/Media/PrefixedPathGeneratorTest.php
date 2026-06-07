<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Media;

use App\Models\Tag;
use App\Support\Media\PrefixedPathGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PrefixedPathGeneratorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_prefixes_media_paths_with_configured_storage_prefix(): void
    {
        config(['media.storage_path_prefix' => 'media']);

        $tag = Tag::factory()->create();
        $fixturePath = 'images/tag-game/path-generator.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($fixturePath));

        $media = $tag->addMedia(public_path($fixturePath))
            ->preservingOriginal()
            ->toMediaCollection('images');

        $generator = new PrefixedPathGenerator;

        $this->assertSame("media/{$media->id}/", $generator->getPath($media));
        $this->assertSame("media/{$media->id}/conversions/", $generator->getPathForConversions($media));
        $this->assertSame("media/{$media->id}/responsive-images/", $generator->getPathForResponsiveImages($media));
    }
}
