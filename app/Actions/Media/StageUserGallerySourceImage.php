<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Jobs\ProcessUserGallerySourceImageJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class StageUserGallerySourceImage
{
    public const string DISK = 'local';

    public const string DIRECTORY = 'media/pending/gallery-sources';

    public function __invoke(Media $media, TemporaryUploadedFile|UploadedFile $sourceFile): void
    {
        $extension = strtolower($sourceFile->guessExtension() ?: 'image');
        $fileName = Str::uuid()->toString().'.'.$extension;
        $stagedPath = Storage::disk(self::DISK)->putFileAs(self::DIRECTORY, $sourceFile, $fileName);

        if ($stagedPath === false) {
            throw new \RuntimeException('Failed to stage the gallery source image for processing.');
        }

        ProcessUserGallerySourceImageJob::dispatch((int) $media->id, $stagedPath)->afterCommit();
    }
}
