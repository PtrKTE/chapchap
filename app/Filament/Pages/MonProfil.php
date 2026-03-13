<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;

/**
 * Page "Mon profil" accessible depuis le menu utilisateur.
 * Affiche les informations du compte connecté en lecture seule.
 * Pas besoin de permission spéciale — chaque utilisateur voit son propre profil.
 */
class MonProfil extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $title = 'Mon profil';

    protected static ?string $slug = 'mon-profil';

    // Page cachée de la navigation sidebar (accessible uniquement via le menu profil)
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.mon-profil';

    public function profileInfolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record(auth()->user())
            ->schema([
                Section::make('Informations personnelles')
                    ->icon('heroicon-o-user')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nom complet')
                            ->icon('heroicon-o-user')
                            ->weight('bold')
                            ->size(TextEntry\TextEntrySize::Large),

                        TextEntry::make('email')
                            ->label('Email')
                            ->icon('heroicon-o-envelope')
                            ->copyable(),

                        TextEntry::make('telephone')
                            ->label('Téléphone')
                            ->icon('heroicon-o-phone')
                            ->placeholder('Non renseigné'),

                        TextEntry::make('profil')
                            ->label('Profil')
                            ->icon('heroicon-o-shield-check')
                            ->badge()
                            ->color(fn ($state) => match ($state?->value ?? '') {
                                'gerant' => 'danger',
                                'resp_operations' => 'warning',
                                'gestionnaire_stock' => 'info',
                                'agent_production' => 'success',
                                'commercial', 'point_de_vente' => 'primary',
                                default => 'gray',
                            }),

                        TextEntry::make('emplacement.nom')
                            ->label('Emplacement')
                            ->icon('heroicon-o-map-pin')
                            ->placeholder('Aucun site'),

                        TextEntry::make('roles.name')
                            ->label('Rôle système')
                            ->icon('heroicon-o-key')
                            ->badge()
                            ->color('gray'),
                    ])->columns(2),

                Section::make('Activité')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Compte créé le')
                            ->dateTime('d/m/Y à H:i'),

                        TextEntry::make('updated_at')
                            ->label('Dernière mise à jour')
                            ->dateTime('d/m/Y à H:i'),
                    ])->columns(2),
            ]);
    }
}
