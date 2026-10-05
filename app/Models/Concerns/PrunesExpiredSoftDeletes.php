<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;

trait PrunesExpiredSoftDeletes
{
    use Prunable;

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $cutoff = now()->subDays((int) config('housekeeping.soft_deleted_retention_days'));

        return static::onlyTrashed()->where('deleted_at', '<=', $cutoff);
    }
}
