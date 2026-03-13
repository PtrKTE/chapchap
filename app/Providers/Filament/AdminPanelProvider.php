<?php

namespace App\Providers\Filament;

use App\Filament\Pages\MonProfil;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Navigation\MenuItem;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Logo affiché dans le header et la page de connexion
            ->brandLogo(asset('images/Logo_chapchap.jpeg'))
            ->brandLogoHeight('3rem')
            ->brandName('ChapChap')
            // Favicon affiché dans l'onglet du navigateur
            ->favicon(asset('images/favicon.ico'))
            ->colors([
                'primary' => Color::Orange,
            ])
            ->sidebarCollapsibleOnDesktop()
            // Menu profil enrichi : rôle, emplacement, lien "Mon profil"
            ->userMenuItems([
                MenuItem::make()
                    ->label(fn () => auth()->user()?->profil?->getLabel() ?? 'Utilisateur')
                    ->icon('heroicon-o-shield-check')
                    ->url('#'),
                MenuItem::make()
                    ->label(fn () => auth()->user()?->emplacement?->nom ?? 'Aucun site')
                    ->icon('heroicon-o-map-pin')
                    ->url('#'),
                'profile' => MenuItem::make()
                    ->label('Mon profil')
                    ->icon('heroicon-o-user-circle')
                    ->url(fn () => MonProfil::getUrl()),
            ])
            // CSS custom
            ->renderHook(
                'panels::head.end',
                fn (): HtmlString => new HtmlString(
                    '<link rel="stylesheet" href="' . asset('css/chapchap-admin.css') . '">'
                )
            )
            // Déconnexion automatique en cas d'inactivité (avec avertissement)
            ->renderHook(
                'panels::body.end',
                fn () => auth()->check()
                    ? view('filament.components.idle-timeout')
                    : ''
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Widgets métier découverts automatiquement dans app/Filament/Widgets/
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
