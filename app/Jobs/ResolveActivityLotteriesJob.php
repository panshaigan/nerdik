<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ActivityLotteryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ResolveActivityLotteriesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public function handle(ActivityLotteryService $lotteryService): void
    {
        $lotteryService->resolveDueActivities();
    }
}
