<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class MonitoringHeartbeat
{
    /**
     * @param  'scheduler'|'worker'  $target
     */
    public function ping(string $target): void
    {
        $url = match ($target) {
            'scheduler' => config('monitoring.scheduler_heartbeat_url'),
            'worker' => config('monitoring.worker_heartbeat_url'),
            default => throw new InvalidArgumentException("Unknown monitoring heartbeat target [{$target}]."),
        };

        if (! is_string($url) || $url === '') {
            return;
        }

        $response = Http::timeout(10)
            ->retry(2, 200)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Monitoring heartbeat [{$target}] failed with HTTP {$response->status()}."
            );
        }
    }
}
