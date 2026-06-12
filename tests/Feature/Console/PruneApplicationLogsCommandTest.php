<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PruneApplicationLogsCommandTest extends TestCase
{
    #[Test]
    public function it_deletes_old_application_log_files(): void
    {
        Config::set('housekeeping.log_retention_days', 14);

        $logsPath = storage_path('logs');
        File::ensureDirectoryExists($logsPath);

        $oldLog = $logsPath.'/housekeeping-old.log';
        $freshLog = $logsPath.'/housekeeping-fresh.log';

        file_put_contents($oldLog, 'old');
        file_put_contents($freshLog, 'fresh');

        touch($oldLog, now()->subDays(30)->getTimestamp());
        touch($freshLog, now()->getTimestamp());

        try {
            $this->artisan('housekeeping:prune-logs')
                ->assertSuccessful();

            $this->assertFileDoesNotExist($oldLog);
            $this->assertFileExists($freshLog);
        } finally {
            @unlink($oldLog);
            @unlink($freshLog);
        }
    }

    #[Test]
    public function dry_run_does_not_delete_old_application_log_files(): void
    {
        Config::set('housekeeping.log_retention_days', 14);

        $logsPath = storage_path('logs');
        File::ensureDirectoryExists($logsPath);

        $oldLog = $logsPath.'/housekeeping-dry-run.log';

        file_put_contents($oldLog, 'old');
        touch($oldLog, now()->subDays(30)->getTimestamp());

        try {
            $this->artisan('housekeeping:prune-logs', ['--dry-run' => true])
                ->assertSuccessful();

            $this->assertFileExists($oldLog);
        } finally {
            @unlink($oldLog);
        }
    }
}
