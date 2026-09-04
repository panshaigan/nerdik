<?php

declare(strict_types=1);

namespace App\Actions\Images;

use Spatie\MediaLibrary\HasMedia;

final class AttachSourceImageFromPath
{
    public function __invoke(HasMedia $model, string $absolutePath): void
    {
        $imageSize = @getimagesize($absolutePath);

        $model->clearMediaCollection('source');
        $model->addMedia($absolutePath)
            ->withCustomProperties([
                'width' => $imageSize !== false ? $imageSize[0] : null,
                'height' => $imageSize !== false ? $imageSize[1] : null,
            ])
            ->toMediaCollection('source');
    }
}
