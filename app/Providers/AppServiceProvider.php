<?php

namespace App\Providers;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Let published Livewire pagination views override package defaults (theme-aware styles).
        View::prependNamespace('livewire', resource_path('views/vendor/livewire'));

        // Keep polymorphic type strings compact and stable across apps/packages.
        Relation::morphMap([
            'activity' => Activity::class,
        ]);

        Blade::if('canModifyEntity', function (mixed $entity): bool {
            if (! $entity instanceof Model) {
                return false;
            }
            $user = auth()->user();

            return $user !== null && $user->canModifyEntity($entity);
        });
    }
}
