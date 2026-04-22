<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Models\Vente;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

/**
 * Page de consultation d'un client.
 *
 * Affiche la fiche complète du client avec :
 * - Ses informations (déjà dans ClientResource::infolist)
 * - Un résumé financier (CA total, impayés)
 * - Les 10 dernières ventes
 */
class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('Modifier'),
        ];
    }

    /**
     * On enrichit l'infolist de base avec une section "Activité commerciale".
     *
     * infolist() complet = infolist de ClientResource + section ventes récentes.
     */
    public function infolist(Infolist $infolist): Infolist
    {
        // Statistiques financières du client
        $client = $this->record;

        $caTotal = $client->ventes()
            ->where('annulee', false)
            ->sum('montant_net');

        $impayes = $client->ventes()
            ->where('annulee', false)
            ->where('montant_restant', '>', 0)
            ->sum('montant_restant');

        $nbVentes = $client->ventes()
            ->where('annulee', false)
            ->count();

        return $infolist->schema([
            // Section 1 : Identification (reprise de ClientResource::infolist)
            Infolists\Components\Section::make('Identification')
                ->icon('heroicon-o-user')
                ->schema([
                    Infolists\Components\TextEntry::make('code')
                        ->label('Code')
                        ->weight('bold')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('nom')
                        ->label('Nom / Raison sociale')
                        ->weight('bold'),
                    Infolists\Components\TextEntry::make('type_client')
                        ->label('Type')
                        ->badge(),
                    Infolists\Components\TextEntry::make('telephone')
                        ->label('Téléphone')
                        ->icon('heroicon-o-phone')
                    Infolists\Components\TextEntry::make('email')
                        ->label('Email')
                        ->icon('heroicon-o-envelope')
                    Infolists\Components\IconEntry::make('actif')
                        ->label('Actif')
                        ->boolean(),
                ])->columns(3),

            // Section 2 : Infos commerciales
            Infolists\Components\Section::make('Commercial & Localisation')
                ->icon('heroicon-o-map-pin')
                ->schema([
                    Infolists\Components\TextEntry::make('adresse')
                        ->label('Adresse')
                    Infolists\Components\TextEntry::make('quartier_zone')
                        ->label('Quartier / Zone')
                    Infolists\Components\TextEntry::make('commercial.name')
                        ->label('Commercial')
                        ->icon('heroicon-o-briefcase')
                    Infolists\Components\TextEntry::make('mode_paiement_habituel')
                        ->label('Mode paiement habituel')
                        ->badge()
                    Infolists\Components\TextEntry::make('contact_principal')
                        ->label('Contact')
                    Infolists\Components\TextEntry::make('conditions_paiement')
                        ->label('Conditions')
                ])->columns(3)
                ->collapsible(),

            // Section 3 : Résumé financier (calculé dynamiquement)
            Infolists\Components\Section::make('Résumé financier')
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Infolists\Components\TextEntry::make('ca_total')
                        ->label('CA total')
                        ->getStateUsing(fn () => number_format((float) $caTotal, 0, ',', ' ') . ' FCFA')
                        ->weight('bold')
                        ->color('primary'),

                    Infolists\Components\TextEntry::make('nb_ventes')
                        ->label('Nb de ventes')
                        ->getStateUsing(fn () => $nbVentes),

                    Infolists\Components\TextEntry::make('impayes')
                        ->label('Impayés en cours')
                        ->getStateUsing(fn () => number_format((float) $impayes, 0, ',', ' ') . ' FCFA')
                        ->weight('bold')
                        ->color(fn () => $impayes > 0 ? 'danger' : 'success'),

                    Infolists\Components\TextEntry::make('date_enregistrement')
                        ->label('Client depuis')
                        ->date('d/m/Y'),
                ])->columns(4),

            // Section 4 : 10 dernières ventes (relation limitée à 10)
            Infolists\Components\Section::make('Dernières ventes')
                ->icon('heroicon-o-shopping-cart')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('ventesRecentes')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('numero_recu')
                                ->label('N° Reçu')
                                ->weight('bold'),
                            Infolists\Components\TextEntry::make('date_vente')
                                ->label('Date')
                                ->dateTime('d/m/Y'),
                            Infolists\Components\TextEntry::make('montant_net')
                                ->label('Montant net')
                                ->numeric(0)
                                ->suffix(' FCFA'),
                            Infolists\Components\TextEntry::make('montant_restant')
                                ->label('Restant')
                                ->numeric(0)
                                ->suffix(' FCFA')
                                ->color(fn ($state) => (float) $state > 0 ? 'danger' : 'success'),
                            Infolists\Components\TextEntry::make('statut_paiement')
                                ->label('Statut')
                                ->badge(),
                        ])->columns(5),
                ])
                ->collapsible(),

            // Section 5 : Notes
            Infolists\Components\Section::make('Notes')
                ->schema([
                    Infolists\Components\TextEntry::make('notes')
                        ->label('')
                        ->placeholder('Aucune note')
                        ->columnSpanFull(),
                ])->collapsible(),
        ]);
    }
}
