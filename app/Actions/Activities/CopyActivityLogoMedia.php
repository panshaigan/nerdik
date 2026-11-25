<?php

declare(strict_types=1);

namespace App\Actions\Activities;

use App\Models\Activity;

final class CopyActivityLogoMedia
{
    /**
     * Copies the entity-owned logo crop (and its crop source) so a duplicate keeps the same image
     * without sharing files that deleting either activity would remove.
     */
    public function __invoke(Activity $source, Activity $target): void
    {
        foreach (['logo', 'source'] as $collection) {
            $media = $source->getFirstMedia($collection);
            if ($media === null) {
                continue;
            }

            $target->clearMediaCollection($collection);
            $media->copy($target, $collection, $media->disk);
        }
    }
}
