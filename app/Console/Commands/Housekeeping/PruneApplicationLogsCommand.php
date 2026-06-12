<?php

declare(strict_types=1);

namespace App\Console\Commands\Housekeeping;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('housekeeping:prune-logs {--dry-run : Report old log files without deleting}')]
#[Description('Delete application log files older than the configured retention period')]
final class PruneApplicationLogsCommand extends Command
{
    public function handle(): int
    {
        $logsPath = storage_path('logs');
        $retentionDays = (int) config('housekeeping.log_retention_days');
        $cutoff = now()->subDays($retentionDays)->getTimestamp();
        $dryRun = (bool) $this->option('dry-run');
        $pruned = 0;

        if (! File::isDirectory($logsPath)) {
            $this->info('Log directory does not exist; nothing to prune.');

            return self::SUCCESS;
        }

        foreach (File::glob($logsPath.'/*.log') ?: [] as $logFile) {
            if (! is_file($logFile)) {
                continue;
            }

            if (filemtime($logFile) >= $cutoff) {
                continue;
            }

            if ($dryRun) {
                $this->line('Would prune log file ['.basename($logFile).'].');
                $pruned++;

                continue;
            }

            File::delete($logFile);
            $this->line('Pruned log file ['.basename($logFile).'].');
            $pruned++;
        }

        $this->info(sprintf(
            'Application log prune complete. pruned=%d%s',
            $pruned,
            $dryRun ? ' (dry-run)' : '',
        ));

        return self::SUCCESS;
    }
}
