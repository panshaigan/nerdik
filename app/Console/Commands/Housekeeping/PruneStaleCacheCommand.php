<?php

declare(strict_types=1);

namespace App\Console\Commands\Housekeeping;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('housekeeping:prune-cache {--dry-run : Report expired cache rows without deleting}')]
#[Description('Delete expired rows from the database cache and cache_locks tables')]
final class PruneStaleCacheCommand extends Command
{
    public function handle(): int
    {
        if (config('cache.default') !== 'database') {
            $this->info('Cache store is not database; nothing to prune.');

            return self::SUCCESS;
        }

        $cutoff = now()->getTimestamp();
        $dryRun = (bool) $this->option('dry-run');
        $pruned = 0;

        foreach (['cache', 'cache_locks'] as $table) {
            $query = DB::table($table)->where('expiration', '<', $cutoff);
            $count = (clone $query)->count();

            if ($count === 0) {
                continue;
            }

            if (! $dryRun) {
                $query->delete();
            }

            $pruned += $count;
        }

        $this->info(sprintf(
            'Cache prune complete. pruned=%d%s',
            $pruned,
            $dryRun ? ' (dry-run)' : '',
        ));

        return self::SUCCESS;
    }
}
