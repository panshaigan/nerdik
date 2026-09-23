<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Actions\Images\StoreSourcePublicImage;
use App\Actions\Media\StageUserGallerySourceImage;
use App\Jobs\ProcessUserGallerySourceImageJob;
use App\Models\User;
use App\Support\Media\UserGalleryCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class ProcessUserGallerySourceImageJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function source_image_is_staged_and_queued_without_being_encoded_in_the_request(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Queue::fake();
        $media = $this->createGalleryMedia();

        app(StageUserGallerySourceImage::class)(
            $media,
            UploadedFile::fake()->image('source.jpg', 1800, 1200),
        );

        Queue::assertPushed(
            ProcessUserGallerySourceImageJob::class,
            function (ProcessUserGallerySourceImageJob $job) use ($media): bool {
                $this->assertSame((int) $media->id, $job->mediaId);
                Storage::disk('local')->assertExists($job->stagedPath);
                Storage::disk('public')->assertMissing(UserGalleryCatalog::sourceRelativePath((int) $media->id));

                return true;
            },
        );
    }

    #[Test]
    public function job_encodes_source_updates_media_and_removes_staged_file(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Queue::fake();
        $media = $this->createGalleryMedia();
        $stagedPath = Storage::disk('local')->putFileAs(
            StageUserGallerySourceImage::DIRECTORY,
            UploadedFile::fake()->image('source.jpg', 1800, 1200),
            'source.jpg',
        );
        $this->assertIsString($stagedPath);

        $job = new ProcessUserGallerySourceImageJob((int) $media->id, $stagedPath);
        $job->handle(app(StoreSourcePublicImage::class));

        $sourcePath = UserGalleryCatalog::sourceRelativePath((int) $media->id);
        Storage::disk('local')->assertMissing($stagedPath);
        Storage::disk('public')->assertExists($sourcePath);
        $this->assertSame(
            $sourcePath,
            $media->fresh()?->getCustomProperty(UserGalleryCatalog::SOURCE_PATH_PROPERTY),
        );
    }

    #[Test]
    public function missing_media_discards_staged_file_without_encoding(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $stagedPath = Storage::disk('local')->putFileAs(
            StageUserGallerySourceImage::DIRECTORY,
            UploadedFile::fake()->image('source.jpg', 800, 600),
            'source.jpg',
        );
        $this->assertIsString($stagedPath);

        $job = new ProcessUserGallerySourceImageJob(999999, $stagedPath);
        $job->handle(app(StoreSourcePublicImage::class));

        Storage::disk('local')->assertMissing($stagedPath);
        Storage::disk('public')->assertMissing(UserGalleryCatalog::sourceRelativePath(999999));
    }

    #[Test]
    public function terminal_failure_removes_staged_file(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $stagedPath = Storage::disk('local')->putFileAs(
            StageUserGallerySourceImage::DIRECTORY,
            UploadedFile::fake()->image('source.jpg', 800, 600),
            'source.jpg',
        );
        $this->assertIsString($stagedPath);
        $sourcePath = UserGalleryCatalog::sourceRelativePath(123);
        Storage::disk('public')->put($sourcePath, 'partial');

        $job = new ProcessUserGallerySourceImageJob(123, $stagedPath);
        $job->failed(new \RuntimeException('failed'));

        Storage::disk('local')->assertMissing($stagedPath);
        Storage::disk('public')->assertMissing($sourcePath);
        $this->assertSame('123', $job->uniqueId());
        $this->assertSame([1, 5, 10], $job->backoff());
    }

    private function createGalleryMedia(): Media
    {
        $user = User::factory()->create();

        return $user
            ->addMedia(UploadedFile::fake()->image('crop.jpg', 1280, 720))
            ->toMediaCollection(UserGalleryCatalog::COLLECTION);
    }
}
