<?php

declare(strict_types=1);

namespace App\Console\Commands\Housekeeping;

use App\Models\UserAvailabilityDateException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('housekeeping:prune-availability-exceptions {--dry-run : Report past availability exceptions without deleting}')]
#[Description('Delete user availability date exceptions older than today minus the configured grace period')]
final class PruneAvailabilityExceptionsCommand extends Command
{
    public function handle(): int
    {
        $graceDays = max(0, (int) config('housekeeping.availability_exceptions_grace_days'));
        $cutoff = now()->startOfDay()->subDays($graceDays)->toDateString();
        $dryRun = (bool) $this->option('dry-run');

        $query = UserAvailabilityDateException::query()->whereDate('date', '<', $cutoff);
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('Availability exception prune complete. pruned=0');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info(sprintf('Availability exception prune complete. pruned=%d (dry-run)', $count));

            return self::SUCCESS;
        }

        $query->delete();

        $this->info(sprintf('Availability exception prune complete. pruned=%d', $count));

        return self::SUCCESS;
    }
}
