<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait RegistersOptimizedImageConversions
{
    /**
     * @param  list<string>  $collections
     */
    protected function registerOptimizedConversionsForCollections(array $collections): void
    {
        $queued = (bool) config('media.queue_conversions', true);

        foreach ($this->conversionFormatsForEnvironment() as $format) {
            $conversion = $this->addMediaConversion($format['name'])
                ->format($format['extension'])
                ->quality((int) config("media.conversion_qualities.{$format['name']}", 85))
                ->fit(Fit::Max, 1536, 1536)
                ->performOnCollections(...$collections);

            if ($this->shouldGenerateResponsiveImages()) {
                $conversion->withResponsiveImages();
            }

            if ($queued) {
                $conversion->queued();
            } else {
                $conversion->nonQueued();
            }
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

    protected function shouldGenerateResponsiveImages(): bool
    {
        if (! app()->environment('testing')) {
            return true;
        }

        return (bool) config('media.testing.generate_responsive_images', true);
    }

    abstract public function registerMediaConversions(?Media $media = null): void;
}
