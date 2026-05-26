<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Actions\Images\StoreCroppedPublicImage;
use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class StoreUploadedEventLogo
{
    private const int COVER_WIDTH = 1280;

    private const int COVER_HEIGHT = 720;

    public function __construct(
        private StoreCroppedPublicImage $storeCroppedPublicImage,
    ) {}

    /**
     * Writes `event-logos/{event_id}.webp` on the public disk and returns the relative path.
     */
    public function __invoke(Event $event, TemporaryUploadedFile|UploadedFile $file): string
    {
        $relativePath = 'event-logos/'.$event->id.'.webp';

        return ($this->storeCroppedPublicImage)(
            $relativePath,
            $file,
            self::COVER_WIDTH,
            self::COVER_HEIGHT,
        );
    }
}
