<?php

namespace App\Providers;

use App\Listeners\LogNotificationEmail;
use App\Listeners\RefreshUserAvatarCache;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use App\Observers\ActivityObserver;
use App\Observers\ActivityProposalObserver;
use App\Observers\ActivityUserObserver;
use App\Observers\SlotObserver;
use Carbon\Carbon;
use Illuminate\Auth\Events\Login;
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
use Illuminate\Validation\Rules\Password;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

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

        $this->app->singleton(ImageManager::class, function (): ImageManager {
            return new ImageManager(new Driver);
        });

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

        Activity::observe(ActivityObserver::class);
        ActivityProposal::observe(ActivityProposalObserver::class);
        ActivityUser::observe(ActivityUserObserver::class);
        Slot::observe(SlotObserver::class);

        Blade::if('canModifyEntity', static function (mixed $entity): bool {
            if (! $entity instanceof Model) {
                return false;
            }
            $user = auth()->user();

            return $user !== null && $user->canModifyEntity($entity);
        });

        EventFacade::listen(NotificationSent::class, LogNotificationEmail::class);
        EventFacade::listen(Login::class, RefreshUserAvatarCache::class);

        RateLimiter::for('registration', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('password.request', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        Password::defaults(function (): Password {
            if (app()->environment('testing')) {
                return Password::min(8);
            }

            return Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised();
        });
    }
}
