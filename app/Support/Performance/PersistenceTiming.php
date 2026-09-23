<?php

namespace App\Support\Performance;

use Illuminate\Support\Facades\Config;
use Laravel\Pulse\Facades\Pulse;

final class PersistenceTiming
{
    private bool $active = false;

    private bool $recorded = false;

    private string $operation = '';

    private int $startedAt = 0;

    private int $checkpointAt = 0;

    /** @var array<string, int> */
    private array $phases = [];

    public function start(string $operation): self
    {
        $this->active = Config::boolean('monitoring.persistence_timing.enabled', true);
        $this->recorded = false;
        $this->operation = $operation;
        $this->phases = [];
        $this->startedAt = hrtime(true);
        $this->checkpointAt = $this->startedAt;

        return $this;
    }

    public function checkpoint(string $phase): void
    {
        if (! $this->active || $this->recorded) {
            return;
        }

        $now = hrtime(true);
        $this->phases[$phase] = ($this->phases[$phase] ?? 0) + $this->millisecondsBetween($this->checkpointAt, $now);
        $this->checkpointAt = $now;
    }

    public function recordIfSlow(): void
    {
        if (! $this->active || $this->recorded) {
            return;
        }

        $this->recorded = true;
        $finishedAt = hrtime(true);
        $total = $this->millisecondsBetween($this->startedAt, $finishedAt);
        $threshold = max(0, (int) Config::get('monitoring.persistence_timing.slow_threshold_ms', 1000));

        if ($total < $threshold) {
            return;
        }

        $unattributed = $this->millisecondsBetween($this->checkpointAt, $finishedAt);
        if ($unattributed > 0) {
            $this->phases['unattributed'] = ($this->phases['unattributed'] ?? 0) + $unattributed;
        }

        $phases = [...$this->phases, 'total' => $total];

        rescue(function () use ($phases): void {
            foreach ($phases as $phase => $duration) {
                Pulse::record('persistence_phase', $this->operation.'|'.$phase, $duration)
                    ->avg()
                    ->max()
                    ->count()
                    ->onlyBuckets();
            }
        }, report: false);
    }

    /** @return array<string, int> */
    public function phaseDurations(): array
    {
        return $this->phases;
    }

    private function millisecondsBetween(int $from, int $to): int
    {
        return (int) round(($to - $from) / 1_000_000);
    }
}
