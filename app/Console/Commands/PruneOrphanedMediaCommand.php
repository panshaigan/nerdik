<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Signature('media:prune-orphans {--dry-run : Report orphans without deleting}')]
#[Description('Delete media records whose parent model no longer exists')]
final class PruneOrphanedMediaCommand extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $pruned = 0;

        Media::query()
            ->orderBy('id')
            ->each(function (Media $media) use ($dryRun, &$pruned): void {
                if ($media->model !== null) {
                    return;
                }

                if ($dryRun) {
                    $this->line("Would prune orphan media [{$media->id}] ({$media->model_type}#{$media->model_id}).");
                    $pruned++;

                    return;
                }

                $media->delete();
                $this->line("Pruned orphan media [{$media->id}].");
                $pruned++;
            });

        $this->info(sprintf(
            'Orphan media prune complete. pruned=%d%s',
            $pruned,
            $dryRun ? ' (dry-run)' : '',
        ));

        return self::SUCCESS;
    }
}
