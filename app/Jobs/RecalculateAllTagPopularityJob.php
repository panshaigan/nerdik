<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\TagPopularityRecalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateAllTagPopularityJob implements ShouldQueue
{
    use Queueable;

    public function handle(TagPopularityRecalculator $recalculator): void
    {
        $recalculator->recalculateAll();
    }
}
