<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Media\ReplaceMediaOriginalWithNormalized;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

final class NormalizeOptimizedMediaOnAdd
{
    /**
     * @var list<string>
     */
    private const COLLECTIONS = ['images', 'logo', 'gallery', 'event_listing'];

    public function __construct(
        private readonly ReplaceMediaOriginalWithNormalized $replaceMediaOriginalWithNormalized,
    ) {}

    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $media = $event->media;

        if (! in_array($media->collection_name, self::COLLECTIONS, true)) {
            return;
        }

        // Responsives are dispatched after this event; only rewrite the original here.
        ($this->replaceMediaOriginalWithNormalized)(
            $media,
            dryRun: false,
            regenerateResponsives: false,
        );
    }
}
