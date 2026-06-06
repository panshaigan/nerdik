<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Actions\Images\StoreCroppedPublicImage;
use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class StoreUploadedEventLogo
{
    private const int COVER_WIDTH = 1280;

    private const int COVER_HEIGHT = 720;

    public function __construct(
        private StoreCroppedPublicImage $storeCroppedPublicImage,
    ) {}

    public function __invoke(Event $event, TemporaryUploadedFile|UploadedFile $file): void
    {
        $tempRelativePath = 'event-logos/temp-'.$event->id.'-'.uniqid('', true).'.webp';

        ($this->storeCroppedPublicImage)(
            $tempRelativePath,
            $file,
            self::COVER_WIDTH,
            self::COVER_HEIGHT,
        );

        $absolutePath = Storage::disk('public')->path($tempRelativePath);

        $event->clearMediaCollection('logo');
        $event->addMedia($absolutePath)
            ->withCustomProperties([
                'width' => self::COVER_WIDTH,
                'height' => self::COVER_HEIGHT,
            ])
            ->toMediaCollection('logo');

        Storage::disk('public')->delete($tempRelativePath);
        $this->deleteLegacyLogoFiles($event);
    }

    private function deleteLegacyLogoFiles(Event $event): void
    {
        if (filled($event->logo_path)) {
            Storage::disk('public')->delete((string) $event->logo_path);
        }

        $canonical = 'event-logos/'.$event->id.'.webp';
        if (Storage::disk('public')->exists($canonical)) {
            Storage::disk('public')->delete($canonical);
        }
    }
}
