<?php

declare(strict_types=1);

namespace App\Console\Commands\Housekeeping;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

#[Signature('housekeeping:prune-livewire-uploads {--dry-run : Report stale uploads without deleting}')]
#[Description('Delete abandoned Livewire temporary upload files')]
final class PruneLivewireUploadsCommand extends Command
{
    public function handle(): int
    {
        if (FileUploadConfiguration::isUsingS3() || FileUploadConfiguration::isUsingGCS()) {
            $this->info('Livewire uploads use object storage; skipping local temp prune.');

            return self::SUCCESS;
        }

        if (! FileUploadConfiguration::shouldCleanupOldUploads()) {
            $this->info('Livewire temporary upload cleanup is disabled in config.');

            return self::SUCCESS;
        }

        $storage = FileUploadConfiguration::storage();
        $directory = FileUploadConfiguration::path();
        $cutoff = now()->subHours((int) config('housekeeping.livewire_tmp_hours'))->getTimestamp();
        $dryRun = (bool) $this->option('dry-run');
        $pruned = 0;

        foreach ($storage->allFiles($directory) as $filePath) {
            if (! $storage->exists($filePath)) {
                continue;
            }

            if ($storage->lastModified($filePath) >= $cutoff) {
                continue;
            }

            if ($dryRun) {
                $this->line("Would prune livewire upload [{$filePath}].");
                $pruned++;

                continue;
            }

            $storage->delete($filePath);
            $this->line("Pruned livewire upload [{$filePath}].");
            $pruned++;
        }

        $this->info(sprintf(
            'Livewire upload prune complete. pruned=%d%s',
            $pruned,
            $dryRun ? ' (dry-run)' : '',
        ));

        return self::SUCCESS;
    }
}
