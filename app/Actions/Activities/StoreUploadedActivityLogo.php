<?php

declare(strict_types=1);

namespace App\Actions\Activities;

use App\Actions\Images\StoreCroppedPublicImage;
use App\Models\Activity;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class StoreUploadedActivityLogo
{
    private const int COVER_WIDTH = 1280;

    private const int COVER_HEIGHT = 720;

    public function __construct(
        private StoreCroppedPublicImage $storeCroppedPublicImage,
    ) {}

    /**
     * Writes `activity-logos/{activity_id}.webp` on the public disk and returns the relative path.
     */
    public function __invoke(Activity $activity, TemporaryUploadedFile|UploadedFile $file): string
    {
        $relativePath = 'activity-logos/'.$activity->id.'.webp';

        return ($this->storeCroppedPublicImage)(
            $relativePath,
            $file,
            self::COVER_WIDTH,
            self::COVER_HEIGHT,
        );
    }
}
