<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Images\StoreSourcePublicImage;
use App\Actions\Media\StageUserGallerySourceImage;
use App\Support\Media\UserGalleryCatalog;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final class ProcessUserGallerySourceImageJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $mediaId,
        public readonly string $stagedPath,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->mediaId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    public function handle(StoreSourcePublicImage $storeSourcePublicImage): void
    {
        $stagingDisk = Storage::disk(StageUserGallerySourceImage::DISK);

        if (! $stagingDisk->exists($this->stagedPath)) {
            return;
        }

        $media = Media::query()->find($this->mediaId);
        if ($media === null) {
            $stagingDisk->delete($this->stagedPath);

            return;
        }

        $sourceRelativePath = UserGalleryCatalog::sourceRelativePath($this->mediaId);
        ($storeSourcePublicImage)($sourceRelativePath, $stagingDisk->path($this->stagedPath));

        $media = Media::query()->find($this->mediaId);
        if ($media === null) {
            Storage::disk('public')->delete($sourceRelativePath);
            $stagingDisk->delete($this->stagedPath);

            return;
        }

        $media->setCustomProperty(UserGalleryCatalog::SOURCE_PATH_PROPERTY, $sourceRelativePath);
        $media->save();
        $stagingDisk->delete($this->stagedPath);
    }

    public function failed(?Throwable $exception): void
    {
        $sourceRelativePath = UserGalleryCatalog::sourceRelativePath($this->mediaId);
        $media = Media::query()->find($this->mediaId);

        if ($media?->getCustomProperty(UserGalleryCatalog::SOURCE_PATH_PROPERTY) !== $sourceRelativePath) {
            Storage::disk('public')->delete($sourceRelativePath);
        }

        Storage::disk(StageUserGallerySourceImage::DISK)->delete($this->stagedPath);
    }
}
