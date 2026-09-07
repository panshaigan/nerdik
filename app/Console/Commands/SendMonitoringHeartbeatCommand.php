<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Monitoring\MonitoringHeartbeat;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monitoring:heartbeat {target : scheduler or worker}')]
#[Description('Ping an external uptime heartbeat URL for the scheduler or queue worker')]
final class SendMonitoringHeartbeatCommand extends Command
{
    public function handle(MonitoringHeartbeat $heartbeat): int
    {
        $target = (string) $this->argument('target');

        if (! in_array($target, ['scheduler', 'worker'], true)) {
            $this->error('Target must be scheduler or worker.');

            return self::FAILURE;
        }

        $heartbeat->ping($target);
        $this->info("Monitoring heartbeat [{$target}] sent.");

        return self::SUCCESS;
    }
}
