<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EnsureApplicationIsHealthy
{
    public function handle(DiagnosingHealth $event): void
    {
        DB::connection()->getPdo();

        $cacheStore = (string) config('cache.default');

        if (in_array($cacheStore, ['array', 'null'], true)) {
            return;
        }

        Cache::store()->put('__nerdik_health_check', 1, 5);
        Cache::store()->forget('__nerdik_health_check');
    }
}
