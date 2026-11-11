<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Enums\AdminNavigationGroup;
use App\Http\Controllers\Admin\SentEmailPreviewController;
use App\Http\Middleware\AuthenticateAdminPanel;
use App\Http\Middleware\TouchMemberPresence;
use App\Support\Filament\ConfigureFilamentDisplay;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    #[\Override]
    public function register(): void
    {
        ConfigureFilamentDisplay::register();

        parent::register();
    }

    #[\Override]
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path((string) config('filament.admin_path'))
            ->favicon(asset('favicon.svg'))
            ->brandLogo(fn () => view('filament.admin.logo'))
            ->brandLogoHeight('2rem')
            ->homeUrl(fn (): string => '/dashboard')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationGroups(AdminNavigationGroup::class)
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                fn (): string => view('filament.admin.hooks.expand-active-navigation-group')->render(),
            )
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                TouchMemberPresence::class,
            ])
            ->authMiddleware([
                AuthenticateAdminPanel::class,
            ])
            ->authenticatedRoutes(function (): void {
                Route::get('/sent-emails/{sentEmail}/preview', SentEmailPreviewController::class)
                    ->name('sent-emails.preview');
            });
    }
}
