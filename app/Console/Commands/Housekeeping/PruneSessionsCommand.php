<?php

declare(strict_types=1);

namespace App\Console\Commands\Housekeeping;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('housekeeping:prune-sessions {--dry-run : Report stale sessions without deleting}')]
#[Description('Delete expired rows from the database sessions table')]
final class PruneSessionsCommand extends Command
{
    public function handle(): int
    {
        if (config('session.driver') !== 'database') {
            $this->info('Session driver is not database; nothing to prune.');

            return self::SUCCESS;
        }

        $cutoff = now()
            ->subMinutes((int) config('session.lifetime'))
            ->subDays((int) config('housekeeping.sessions_grace_days'))
            ->getTimestamp();

        $query = DB::table('sessions')->where('last_activity', '<', $cutoff);
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('Session prune complete. pruned=0');

            return self::SUCCESS;
        }

        if ((bool) $this->option('dry-run')) {
            $this->info(sprintf('Session prune complete. pruned=%d (dry-run)', $count));

            return self::SUCCESS;
        }

        $query->delete();

        $this->info(sprintf('Session prune complete. pruned=%d', $count));

        return self::SUCCESS;
    }
}
