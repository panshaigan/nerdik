<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Tag;
use App\Support\Media\SimpleMediaFileNamer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AttachesFixtureMedia;
use Tests\TestCase;

final class NormalizeMediaOriginalsCommandTest extends TestCase
{
    use AttachesFixtureMedia;
    use RefreshDatabase;

    #[Test]
    public function it_normalizes_jpeg_originals_to_webp(): void
    {
        $tag = Tag::factory()->create();
        $media = $this->attachTagSampleMedia($tag, 'tests/fixtures/fixture-normalize.jpg');

        $jpegFixture = base_path('tests/fixtures/tag-sample.jpg');
        $oldPath = $media->getPath();
        $media->file_name = 'tag-sample.jpg';
        $media->mime_type = 'image/jpeg';
        $media->saveQuietly();

        $target = $media->getPath();
        File::ensureDirectoryExists(dirname($target));
        File::copy($jpegFixture, $target);
        $media->size = File::size($target);
        $media->saveQuietly();

        if ($oldPath !== $target && File::isFile($oldPath)) {
            File::delete($oldPath);
        }

        $this->artisan('media:normalize-originals', [
            '--force' => true,
            '--collection' => ['images'],
        ])->assertSuccessful();

        $media->refresh();

        $this->assertSame('image/webp', $media->mime_type);
        $this->assertSame(SimpleMediaFileNamer::ORIGINAL_FILE_NAME, $media->file_name);
        $this->assertTrue($media->hasResponsiveImages());
    }

    #[Test]
    public function dry_run_does_not_rewrite_files(): void
    {
        $tag = Tag::factory()->create();
        $media = $this->attachTagSampleMedia($tag);
        $path = $media->getPath();
        $before = File::size($path);

        $this->artisan('media:normalize-originals', [
            '--dry-run' => true,
            '--force' => true,
            '--collection' => ['images'],
        ])->assertSuccessful();

        $this->assertSame($before, File::size($path));
    }
}
