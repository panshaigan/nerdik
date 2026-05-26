<?php

declare(strict_types=1);

namespace App\Actions\Seeders;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;

final class AttachModelMediaFromPublic
{
    /**
     * @param  list<string>  $sources  Paths relative to the public directory.
     * @param  array<string, mixed>  $extraCustomProperties
     */
    public function __invoke(HasMedia $model, array $sources, array $extraCustomProperties = []): void
    {
        foreach ($sources as $source) {
            $this->attachSource($model, $source, $extraCustomProperties);
        }
    }

    /**
     * @param  array<string, mixed>  $extraCustomProperties
     */
    private function attachSource(HasMedia $model, string $source, array $extraCustomProperties): void
    {
        $absolutePath = public_path($source);

        if (! File::isFile($absolutePath)) {
            throw new RuntimeException("Seed image not found at public path [{$source}].");
        }

        if ($this->alreadyAttached($model, $source, $extraCustomProperties)) {
            return;
        }

        $imageSize = @getimagesize($absolutePath);

        $model->addMedia($absolutePath)
            ->preservingOriginal()
            ->withCustomProperties(array_merge([
                'seed_source' => $source,
                'width' => $imageSize !== false ? $imageSize[0] : null,
                'height' => $imageSize !== false ? $imageSize[1] : null,
            ], $extraCustomProperties))
            ->toMediaCollection('images');
    }

    /**
     * @param  array<string, mixed>  $extraCustomProperties
     */
    private function alreadyAttached(HasMedia $model, string $source, array $extraCustomProperties): bool
    {
        $listingRole = $extraCustomProperties['listing_role'] ?? null;

        if (is_string($listingRole) && $listingRole !== '') {
            return $model->media()
                ->where('collection_name', 'images')
                ->where('custom_properties->listing_role', $listingRole)
                ->exists();
        }

        return $model->media()
            ->where('collection_name', 'images')
            ->where('custom_properties->seed_source', $source)
            ->exists();
    }
}
