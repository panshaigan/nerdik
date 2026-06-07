<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Ui\EventListingImageResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Signature('media:migrate-event-listing-collection {--dry-run : Report changes without updating records}')]
#[Description('Move legacy event listing catalog media from images collection to event_listing')]
final class MigrateEventListingCollectionCommand extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $migrated = 0;

        Media::query()
            ->where('collection_name', 'images')
            ->where('custom_properties->listing_role', EventListingImageResolver::LISTING_ROLE)
            ->orderBy('id')
            ->each(function (Media $media) use ($dryRun, &$migrated): void {
                if ($dryRun) {
                    $this->line("Would move media [{$media->id}] to event_listing collection.");
                    $migrated++;

                    return;
                }

                $media->collection_name = EventListingImageResolver::EVENT_LISTING_COLLECTION;
                $media->save();
                $this->line("Moved media [{$media->id}] to event_listing collection.");
                $migrated++;
            });

        $this->info(sprintf(
            'Event listing collection migration complete. migrated=%d%s',
            $migrated,
            $dryRun ? ' (dry-run)' : '',
        ));

        return self::SUCCESS;
    }
}
