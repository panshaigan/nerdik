<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait InteractsWithAvatarImage
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $queued = (bool) config('media.queue_conversions', true)
            && ! $this->shouldRunAvatarConversionsSynchronously();

        $quality = (int) config('media.conversion_qualities.webp', 85);

        foreach ($this->avatarConversionSizes() as $name => $size) {
            $conversion = $this->addMediaConversion($name)
                ->format('webp')
                ->quality($quality)
                ->fit(Fit::Crop, $size, $size)
                ->performOnCollections('avatar');

            if ($queued) {
                $conversion->queued();
            } else {
                $conversion->nonQueued();
            }
        }
    }

    public function avatarConversionsPending(): bool
    {
        $media = $this->getFirstMedia('avatar');

        if ($media === null) {
            return false;
        }

        foreach (array_keys($this->avatarConversionSizes()) as $conversionName) {
            if (! $media->hasGeneratedConversion($conversionName)) {
                return true;
            }
        }

        return false;
    }

    public function pendingAvatarOriginalUrl(): ?string
    {
        if (! $this->avatarConversionsPending()) {
            return null;
        }

        return $this->getFirstMedia('avatar')?->getUrl();
    }

    /**
     * @return array<string, int>
     */
    public function avatarConversionSizes(): array
    {
        /** @var array<string, int> $conversions */
        $conversions = config('media.avatar_conversions', []);

        return $conversions;
    }

    protected function shouldRunAvatarConversionsSynchronously(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        return in_array('tags:seed-images', $_SERVER['argv'] ?? [], true);
    }
}
