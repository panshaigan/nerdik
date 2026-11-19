<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Tag;
use App\Support\Media\SimpleMediaFileNamer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;
use Tests\Support\AttachesFixtureMedia;
use Tests\TestCase;

final class RenameSimpleMediaFilenamesCommandTest extends TestCase
{
    use AttachesFixtureMedia;
    use RefreshDatabase;

    #[Test]
    public function it_renames_original_and_regenerates_simple_responsive_names(): void
    {
        $tag = Tag::factory()->create();
        $media = $this->attachTagSampleMedia($tag);

        $originalOnDisk = $media->getPath();
        $directory = dirname($originalOnDisk);
        $noisyOriginalPath = $directory.DIRECTORY_SEPARATOR.'noisy_upload_stem.webp';

        if ($originalOnDisk !== $noisyOriginalPath) {
            File::move($originalOnDisk, $noisyOriginalPath);
        }

        $media->file_name = 'noisy_upload_stem.webp';
        $media->saveQuietly();

        $responsiveDir = PathGeneratorFactory::create($media)->getPathForResponsiveImages($media);
        $disk = Storage::disk($media->disk);
        $legacyResponsive = $responsiveDir.'noisy___media_library_original_384_216.webp';
        $disk->put($legacyResponsive, 'legacy-bytes');
        $media->responsive_images = [
            'media_library_original' => [
                'urls' => [basename($legacyResponsive)],
            ],
        ];
        $media->saveQuietly();

        $this->artisan('media:rename-simple-filenames', [
            '--force' => true,
            '--collection' => ['images'],
        ])->assertSuccessful();

        $media->refresh();

        $this->assertSame(SimpleMediaFileNamer::ORIGINAL_FILE_NAME, $media->file_name);
        $this->assertTrue(File::isFile($media->getPath()));
        $this->assertFalse($disk->exists($legacyResponsive));
        $this->assertTrue($media->hasResponsiveImages());

        $urls = $media->responsive_images['media_library_original']['urls'] ?? [];
        $this->assertNotEmpty($urls);

        foreach ($urls as $fileName) {
            $this->assertMatchesRegularExpression(
                '/^'.preg_quote(SimpleMediaFileNamer::RESPONSIVE_BASENAME, '/').'_\d+\.webp$/',
                $fileName,
            );
            $this->assertStringNotContainsString('___', $fileName);
        }
    }
}
