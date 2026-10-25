<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\SystemHealthOverview;
use App\Services\Admin\Maintenance;
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
use Filament\View\PanelsRenderHook;
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
            // In local development the title ends with [DEV] and the logo has the red DEV label, like the app itself.
            ->brandName(fn (): string => 'BAS SC Helper'.(app()->environment('local') ? ' [DEV]' : ''))
            ->brandLogo(fn () => asset(app()->environment('local') ? 'logo-dev.svg' : 'logo.svg'))
            ->brandLogoHeight('1.75rem')
            ->homeUrl('/dashboard')
            ->navigationItems([
                NavigationItem::make('App dashboard')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->url('/dashboard')
                    ->sort(-10),
            ])
            ->favicon(asset('favicon.svg'))
            // No top bar: the logo sits at the top of the sidebar and the profile menu at the bottom of it.
            ->topbar(false)
            ->navigationGroups(['People', 'Monitoring', 'System'])
            // The sidebar gets the colour of the app header (gray 900) instead of the page colour. On a phone there is
            // a bar like the app header, with the logo on the left and a button on the right that opens the sidebar.
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => '<style>'
                .'.fi-sidebar{background-color:var(--gray-900);border-inline-end:1px solid rgb(255 255 255 / .08)}'
                .'.bas-topbar{display:none}'
                .'@media (max-width:1023.98px){'
                .'.bas-topbar{position:sticky;top:0;z-index:20;display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;background-color:var(--gray-900);border-bottom:1px solid rgb(255 255 255 / .1)}'
                .'.bas-topbar-logo{display:flex;align-items:center}'
                .'.bas-topbar-logo img{height:1.75rem;width:auto}'
                .'.bas-topbar-button{display:flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;margin-right:-.5rem;border:0;border-radius:.5rem;background:transparent;color:#fff;cursor:pointer}'
                .'.bas-topbar-button:hover{background-color:rgb(255 255 255 / .06)}'
                .'.bas-topbar-button svg{width:1.5rem;height:1.5rem}'
                .'.bas-sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap}'
                .'.fi-layout-sidebar-toggle-btn-ctn{display:none}'
                .'}'
                .'.bas-dev-footer{position:fixed;inset-inline:0;bottom:0;z-index:40;padding:.5rem 0;text-align:center;font:800 .75rem system-ui,sans-serif;letter-spacing:.1em;text-transform:uppercase;color:#ef4444;background-color:rgb(29 29 26 / .9);pointer-events:none}'
                .'@media (min-width:1024px){.bas-dev-footer{inset-inline-start:var(--sidebar-width,20rem)}}'
                .'</style>')
            ->renderHook(PanelsRenderHook::PAGE_START, fn (): string => app(Maintenance::class)->isOn()
                ? '<div style="margin-bottom:1rem;border-radius:.5rem;background:rgba(245,158,11,.15);padding:.5rem 1rem;color:#fde68a;font-size:.875rem">Maintenance mode is on: only admins can use the app right now.</div>'
                : '')
            ->renderHook(PanelsRenderHook::BODY_START, fn (): string => view('filament.mobile-topbar')->render())
            // The red development footer of the app, next to the sidebar on a computer.
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => app()->environment('local') ? '<footer class="bas-dev-footer">Development environment</footer>' : '')
            ->darkMode(true, isForced: true)
            ->colors([
                'primary' => Color::hex('#1da15e'),
                // Warm greys that match the app: 950 is the page background of the app (#1d1d1a) and
                // 900 the colour of its header and cards (#262623), which the panel uses for its page and sidebar.
                'gray' => [
                    50 => '#f6f6f5',
                    100 => '#ebebe9',
                    200 => '#d6d7d5',
                    300 => '#b8bab8',
                    400 => '#9a9b97',
                    500 => '#7b7c77',
                    600 => '#5d5e59',
                    700 => '#42423d',
                    800 => '#32322e',
                    900 => '#262623',
                    950 => '#1d1d1a',
                ],
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
                NeedsAttention::class,
                SystemHealthOverview::class,
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
