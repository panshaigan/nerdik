<?php

namespace App\Providers;

use App\Listeners\LogNotificationEmail;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Place;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        parent::register();
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
            'event' => Event::class,
            'organization' => Organization::class,
            'place' => Place::class,
            'user' => User::class,
            'activity_type' => ActivityType::class,
        ]);

        // Ensure Carbon uses the current app locale for translated month/day names.
        Carbon::setLocale(app()->getLocale());

        Blade::if('canModifyEntity', static function (mixed $entity): bool {
            if (! $entity instanceof Model) {
                return false;
            }
            $user = auth()->user();

            return $user !== null && $user->canModifyEntity($entity);
        });

        EventFacade::listen(NotificationSent::class, LogNotificationEmail::class);

        RateLimiter::for('registration', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('password.request', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
