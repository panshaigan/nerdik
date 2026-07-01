<?php

namespace App\Support\Filament;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class FilamentSearch
{
    public static function likeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function applyBlankSearchGuard(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query->whereRaw('0 = 1');
        }

        return $query;
    }
}
