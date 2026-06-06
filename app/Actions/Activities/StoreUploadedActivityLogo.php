<?php

declare(strict_types=1);

namespace App\Actions\Activities;

use App\Actions\Images\StoreCroppedPublicImage;
use App\Models\Activity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class StoreUploadedActivityLogo
{
    private const int COVER_WIDTH = 1280;

    private const int COVER_HEIGHT = 720;

    public function __construct(
        private StoreCroppedPublicImage $storeCroppedPublicImage,
    ) {}

    public function __invoke(Activity $activity, TemporaryUploadedFile|UploadedFile $file): void
    {
        $tempRelativePath = 'activity-logos/temp-'.$activity->id.'-'.uniqid('', true).'.webp';

        ($this->storeCroppedPublicImage)(
            $tempRelativePath,
            $file,
            self::COVER_WIDTH,
            self::COVER_HEIGHT,
        );

        $absolutePath = Storage::disk('public')->path($tempRelativePath);

        $activity->clearMediaCollection('logo');
        $activity->addMedia($absolutePath)
            ->withCustomProperties([
                'width' => self::COVER_WIDTH,
                'height' => self::COVER_HEIGHT,
            ])
            ->toMediaCollection('logo');

        Storage::disk('public')->delete($tempRelativePath);
        $this->deleteLegacyLogoFiles($activity);
    }

    private function deleteLegacyLogoFiles(Activity $activity): void
    {
        if (filled($activity->logo_path)) {
            Storage::disk('public')->delete((string) $activity->logo_path);
        }

        $canonical = 'activity-logos/'.$activity->id.'.webp';
        if (Storage::disk('public')->exists($canonical)) {
            Storage::disk('public')->delete($canonical);
        }
    }
}
