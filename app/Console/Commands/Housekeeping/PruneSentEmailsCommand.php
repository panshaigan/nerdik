<?php

declare(strict_types=1);

namespace App\Console\Commands\Housekeeping;

use App\Models\SentEmail;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('housekeeping:prune-sent-emails {--dry-run : Report old sent emails without deleting}')]
#[Description('Delete sent email log rows and stored bodies older than the configured retention period')]
final class PruneSentEmailsCommand extends Command
{
    public function handle(): int
    {
        $retentionDays = (int) config('housekeeping.sent_email_retention_days');
        $cutoff = now()->subDays($retentionDays);
        $dryRun = (bool) $this->option('dry-run');
        $pruned = 0;
        $disk = Storage::disk('email_logs');

        SentEmail::query()
            ->where('sent_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($emails) use ($dryRun, $disk, &$pruned): void {
                $paths = $emails
                    ->flatMap(fn (SentEmail $email): array => [$email->html_path, $email->text_path])
                    ->filter(fn (?string $path): bool => is_string($path) && $path !== '')
                    ->unique()
                    ->values();

                if ($dryRun) {
                    $pruned += $emails->count();

                    return;
                }

                if ($paths->isNotEmpty()) {
                    $disk->delete($paths->all());
                }

                SentEmail::query()->whereIn('id', $emails->modelKeys())->delete();
                $pruned += $emails->count();
            });

        $this->info(sprintf(
            'Sent email prune complete. pruned=%d%s',
            $pruned,
            $dryRun ? ' (dry-run)' : '',
        ));

        return self::SUCCESS;
    }
}
