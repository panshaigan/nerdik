<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\TagPopularityRecalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateTagPopularityJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $tagIds
     */
    public function __construct(public array $tagIds) {}

    public function handle(TagPopularityRecalculator $recalculator): void
    {
        $recalculator->recalculateForTagIds($this->tagIds);
    }
}
