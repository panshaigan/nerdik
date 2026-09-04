<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Seeders\AttachModelMediaFromPublic;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait AttachesFixtureMedia
{
    /**
     * Attach the shared tag-sample fixture without writing into public/.
     */
    protected function attachTagSampleMedia(
        HasMedia $model,
        string $seedSource = 'tests/fixtures/tag-sample.jpg',
        string $collection = 'images',
    ): Media {
        app(AttachModelMediaFromPublic::class)->attachFile(
            $model,
            base_path('tests/fixtures/tag-sample.jpg'),
            $seedSource,
            collection: $collection,
        );

        $media = $model->refresh()->getFirstMedia($collection);
        $this->assertNotNull($media);

        return $media;
    }
}
