<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Media;

use App\Actions\Media\AttachOptimizedImage;
use App\Models\Tag;
use App\Support\Media\SimpleMediaFileNamer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;
use Tests\Support\AssertsResponsiveMedia;
use Tests\TestCase;

final class AttachOptimizedImageTest extends TestCase
{
    use AssertsResponsiveMedia;
    use RefreshDatabase;

    #[Test]
    public function it_attaches_normalized_webp_original_with_responsives(): void
    {
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
        $this->assertSame('image/webp', $media->mime_type);
        $this->assertSame(SimpleMediaFileNamer::ORIGINAL_FILE_NAME, $media->file_name);
        $this->assertFalse($media->hasGeneratedConversion('webp'));
        $media->refresh();
        $this->assertMediaHasResponsiveConversions($media);
        $this->assertTrue($media->hasResponsiveImages());

        $responsiveDir = PathGeneratorFactory::create($media)->getPathForResponsiveImages($media);
        $disk = Storage::disk($media->disk);

        $urls = $media->responsive_images['media_library_original']['urls'] ?? [];
        $this->assertNotEmpty($urls);

        foreach ($urls as $fileName) {
            $this->assertMatchesRegularExpression(
                '/^'.preg_quote(SimpleMediaFileNamer::RESPONSIVE_BASENAME, '/').'_\d+\.webp$/',
                $fileName,
            );
            $this->assertStringNotContainsString('___', $fileName);
            $this->assertTrue($disk->exists($responsiveDir.$fileName));
        }
    }
}
