<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Signature('media:migrate-storage-paths {--dry-run : Report changes without moving files}')]
#[Description('Move existing Spatie media directories into the configured storage path prefix subfolder')]
final class MigrateMediaStoragePathsCommand extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $prefix = trim((string) config('media.storage_path_prefix', 'media'), '/');

        if ($prefix === '') {
            $this->warn('media.storage_path_prefix is empty; nothing to migrate.');

            return self::SUCCESS;
        }

        $migrated = 0;
        $skipped = 0;
        $missing = 0;

        Media::query()
            ->orderBy('id')
            ->each(function (Media $media) use ($prefix, $dryRun, &$migrated, &$skipped, &$missing): void {
                $oldPath = (string) $media->getKey();
                $newPath = $prefix.'/'.$media->getKey();

                $diskRoot = rtrim(Storage::disk($media->disk)->path(''), DIRECTORY_SEPARATOR);
                $oldAbsolute = $diskRoot.DIRECTORY_SEPARATOR.$oldPath;
                $newAbsolute = $diskRoot.DIRECTORY_SEPARATOR.$newPath;

                if (! File::isDirectory($oldAbsolute)) {
                    $missing++;

                    return;
                }

                if (File::isDirectory($newAbsolute)) {
                    $skipped++;

                    return;
                }

                if ($dryRun) {
                    $this->line("Would move [{$oldPath}] → [{$newPath}] on disk [{$media->disk}].");
                    $migrated++;

                    return;
                }

                File::ensureDirectoryExists($diskRoot.DIRECTORY_SEPARATOR.$prefix);
                File::moveDirectory($oldAbsolute, $newAbsolute);
                $this->line("Moved [{$oldPath}] → [{$newPath}] on disk [{$media->disk}].");
                $migrated++;
            });

        $this->info(sprintf(
            'Storage path migration complete. migrated=%d skipped=%d missing=%d%s',
            $migrated,
            $skipped,
            $missing,
            $dryRun ? ' (dry-run)' : '',
        ));

        return self::SUCCESS;
    }
}
