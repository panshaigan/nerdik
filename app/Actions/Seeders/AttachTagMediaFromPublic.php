<?php

declare(strict_types=1);

namespace App\Actions\Seeders;

use App\Models\Tag;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class AttachTagMediaFromPublic
{
    /**
     * @param  list<string>  $sources  Paths relative to the public directory.
     */
    public function __invoke(Tag $tag, array $sources): void
    {
        foreach ($sources as $source) {
            $this->attachSource($tag, $source);
        }
    }

    private function attachSource(Tag $tag, string $source): void
    {
        $absolutePath = public_path($source);

        if (! File::isFile($absolutePath)) {
            throw new RuntimeException("Tag seed image not found at public path [{$source}].");
        }

        if ($this->hasSeedSource($tag, $source)) {
            return;
        }

        $imageSize = @getimagesize($absolutePath);

        $previousQueueConversions = config('media.queue_conversions');
        $previousQueueConversionsByDefault = config('media-library.queue_conversions_by_default');

        config([
            'media.queue_conversions' => false,
            'media-library.queue_conversions_by_default' => false,
        ]);

        try {
            $tag->addMedia($absolutePath)
                ->preservingOriginal()
                ->withCustomProperties([
                    'seed_source' => $source,
                    'width' => $imageSize !== false ? $imageSize[0] : null,
                    'height' => $imageSize !== false ? $imageSize[1] : null,
                ])
                ->toMediaCollection('images');
        } finally {
            config([
                'media.queue_conversions' => $previousQueueConversions,
                'media-library.queue_conversions_by_default' => $previousQueueConversionsByDefault,
            ]);
        }
    }

    private function hasSeedSource(Tag $tag, string $source): bool
    {
        return $tag->media()
            ->where('collection_name', 'images')
            ->where('custom_properties->seed_source', $source)
            ->exists();
    }
}
