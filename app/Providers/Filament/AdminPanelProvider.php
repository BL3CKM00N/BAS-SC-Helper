<?php

namespace App\Providers\Filament;

use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // No login of its own: unauthenticated visitors are sent to the login card on "/".
            ->brandName('BAS SC Helper')
            ->brandLogo(fn () => asset('logo.svg'))
            ->brandLogoHeight('1.75rem')
            ->homeUrl('/dashboard')
            ->navigationItems([
                NavigationItem::make('App dashboard')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->url('/dashboard')
                    ->sort(-10),
            ])
            ->favicon(asset('favicon.svg'))
            ->darkMode(true, isForced: true)
            ->colors([
                'primary' => Color::hex('#1da15e'),
            ])
            ->userMenuItems([
                Action::make('account')
                    ->label('Account')
                    ->icon('heroicon-o-user-circle')
                    ->url('/account'),
                Action::make('dashboard')
                    ->label('Dashboard')
                    ->icon('heroicon-o-home')
                    ->url('/dashboard'),
                'logout' => Action::make('logout')
                    ->label('Sign out')
                    ->icon('heroicon-o-arrow-left-on-rectangle')
                    ->url(fn () => route('logout'))
                    ->postToUrl(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
