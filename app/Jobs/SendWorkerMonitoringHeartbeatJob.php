<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Monitoring\MonitoringHeartbeat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWorkerMonitoringHeartbeatJob implements ShouldQueue
{
    use Queueable;

    public function handle(MonitoringHeartbeat $heartbeat): void
    {
        $heartbeat->ping('worker');
    }
}
