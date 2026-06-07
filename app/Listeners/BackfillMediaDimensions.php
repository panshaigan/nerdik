<?php

declare(strict_types=1);

namespace App\Listeners;

use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

final class BackfillMediaDimensions
{
    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $media = $event->media;

        $width = $media->getCustomProperty('width');
        $height = $media->getCustomProperty('height');

        if (is_numeric($width) && is_numeric($height)) {
            return;
        }

        $path = $media->getPath();

        if (! is_file($path)) {
            return;
        }

        $imageSize = @getimagesize($path);

        if ($imageSize === false) {
            return;
        }

        $media->setCustomProperty('width', $imageSize[0]);
        $media->setCustomProperty('height', $imageSize[1]);
        $media->save();
    }
}
