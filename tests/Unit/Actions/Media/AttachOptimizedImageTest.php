<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Media;

use App\Actions\Media\AttachOptimizedImage;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AssertsResponsiveMedia;
use Tests\TestCase;

final class AttachOptimizedImageTest extends TestCase
{
    use AssertsResponsiveMedia;
    use RefreshDatabase;

    #[Test]
    public function it_attaches_media_with_dimensions_and_responsive_conversions(): void
    {
        config(['media.test_profile' => 'full']);

        $tag = Tag::factory()->create();
        $fixture = base_path('tests/fixtures/tag-sample.jpg');

        $media = app(AttachOptimizedImage::class)(
            $tag,
            $fixture,
            'images',
            ['seed_source' => 'tests/fixtures/tag-sample.jpg'],
        );

        $this->assertSame('tests/fixtures/tag-sample.jpg', $media->getCustomProperty('seed_source'));
        $this->assertNotNull($media->getCustomProperty('width'));
        $this->assertNotNull($media->getCustomProperty('height'));
        $this->assertMediaHasResponsiveConversions($media->refresh());
    }
}
