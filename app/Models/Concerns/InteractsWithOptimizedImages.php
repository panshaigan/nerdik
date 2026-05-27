<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait InteractsWithOptimizedImages
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $queued = (bool) config('media.queue_conversions', true);

        foreach ($this->conversionFormatsForEnvironment() as $format) {
            $this->registerOptimizedImageConversion(
                $format['name'],
                $format['extension'],
                $queued,
            );
        }
    }

    /**
     * @return list<array{name: string, extension: string}>
     */
    protected function conversionFormatsForEnvironment(): array
    {
        if (! app()->environment('testing')) {
            return [
                ['name' => 'avif', 'extension' => 'avif'],
                ['name' => 'webp', 'extension' => 'webp'],
                ['name' => 'jpeg', 'extension' => 'jpg'],
            ];
        }

        $formats = config('media.test_profile', 'minimal') === 'full'
            ? config('media.full_test_formats', ['avif', 'webp', 'jpeg'])
            : config('media.testing.conversion_formats', ['webp']);

        return array_map(
            fn (string $name): array => [
                'name' => $name,
                'extension' => $name === 'jpeg' ? 'jpg' : $name,
            ],
            $formats,
        );
    }

    protected function registerOptimizedImageConversion(string $name, string $format, bool $queued): void
    {
        $conversion = $this->addMediaConversion($name)
            ->format($format)
            ->quality((int) config("media.conversion_qualities.{$name}", 85))
            ->fit(Fit::Max, 1536, 1536)
            ->performOnCollections('images');

        if ($this->shouldGenerateResponsiveImages()) {
            $conversion->withResponsiveImages();
        }

        if ($queued) {
            $conversion->queued();
        } else {
            $conversion->nonQueued();
        }
    }

    protected function shouldGenerateResponsiveImages(): bool
    {
        if (! app()->environment('testing')) {
            return true;
        }

        return (bool) config('media.testing.generate_responsive_images', true);
    }
}
