<?php

declare(strict_types=1);

namespace App\Actions\Seeders;

use App\Actions\Media\AttachOptimizedImage;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;

final class AttachModelMediaFromPublic
{
    public function __construct(
        private readonly AttachOptimizedImage $attachOptimizedImage,
    ) {}

    /**
     * @param  list<string>  $sources  Paths relative to the public directory.
     * @param  array<string, mixed>  $extraCustomProperties
     */
    public function __invoke(
        HasMedia $model,
        array $sources,
        array $extraCustomProperties = [],
        string $collection = 'images',
    ): void {
        foreach ($sources as $source) {
            $absolutePath = public_path($source);

            if (! File::isFile($absolutePath)) {
                throw new RuntimeException("Seed image not found at public path [{$source}].");
            }

            $this->attachFile($model, $absolutePath, $source, $extraCustomProperties, $collection);
        }
    }

    /**
     * @param  array<string, mixed>  $extraCustomProperties
     */
    public function attachFile(
        HasMedia $model,
        string $absolutePath,
        string $seedSource,
        array $extraCustomProperties = [],
        string $collection = 'images',
    ): void {
        if (! File::isFile($absolutePath)) {
            throw new RuntimeException("Seed image not found at [{$absolutePath}].");
        }

        if ($this->alreadyAttached($model, $seedSource, $collection, $extraCustomProperties)) {
            return;
        }

        ($this->attachOptimizedImage)(
            $model,
            $absolutePath,
            $collection,
            array_merge(['seed_source' => $seedSource], $extraCustomProperties),
        );
    }

    /**
     * @param  array<string, mixed>  $extraCustomProperties
     */
    private function alreadyAttached(
        HasMedia $model,
        string $seedSource,
        string $collection,
        array $extraCustomProperties,
    ): bool {
        unset($extraCustomProperties);

        return $model->media()
            ->where('collection_name', $collection)
            ->where('custom_properties->seed_source', $seedSource)
            ->exists();
    }
}
