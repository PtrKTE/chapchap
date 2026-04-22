<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ModePaiement;
use App\Filament\Resources\PaiementResource\Pages;
use App\Models\Paiement;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Resource Filament en LECTURE SEULE pour l'historique des paiements.
 *
 * Les paiements sont créés automatiquement :
 * - À la création d'une vente (paiement initial)
 * - Via le bouton "Encaisser" sur la page d'édition d'une vente
 *
 * Cette page sert au suivi financier : qui a payé quoi, quand, par quel moyen.
 */
class PaiementResource extends Resource
{
    protected static ?string $model = Paiement::class;

    // Icône "billets" pour les paiements
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Commercial';

    protected static ?string $modelLabel = 'Paiement';

    protected static ?string $pluralModelLabel = 'Paiements';

    protected static ?int $navigationSort = 3;

    /**
     * Infolist : ce qui s'affiche quand on clique sur "Voir" un paiement.
     * Sans cette méthode, la modale de visualisation est vide.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Détails du paiement')
                ->schema([
                    Infolists\Components\TextEntry::make('vente.numero_recu')
                        ->label('N° Reçu de la vente'),

                    Infolists\Components\TextEntry::make('vente.client.nom')
                        ->label('Client'),

                    Infolists\Components\TextEntry::make('date_paiement')
                        ->label('Date du paiement')
                        ->dateTime('d/m/Y H:i'),

                    Infolists\Components\TextEntry::make('montant')
                        ->label('Montant')
                        ->numeric(0)
                        ->suffix(' FCFA')
                        ->weight('bold')
                        ->color('success'),

                    Infolists\Components\TextEntry::make('mode_paiement')
                        ->label('Mode de paiement')
                        ->badge(),

                    Infolists\Components\TextEntry::make('reference_paiement')
                        ->label('Référence')

                    Infolists\Components\TextEntry::make('observations')
                        ->label('Observations')
                        ->columnSpanFull(),

                    Infolists\Components\TextEntry::make('createdBy.name')
                        ->label('Encaissé par'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with(['vente.client', 'createdBy']))
            ->defaultSort('date_paiement', 'desc')
            ->columns([
                // N° de reçu de la vente associée
                Tables\Columns\TextColumn::make('vente.numero_recu')
                    ->label('N° Reçu')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // Client (via la vente)
                Tables\Columns\TextColumn::make('vente.client.nom')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('date_paiement')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('montant')
                    ->label('Montant')
                    ->numeric(0)
                    ->suffix(' FCFA')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()
                        ->label('Total')
                        ->numeric(0)
                        ->suffix(' FCFA')
                    ),

                Tables\Columns\TextColumn::make('mode_paiement')
                    ->label('Mode')
                    ->badge(),

                Tables\Columns\TextColumn::make('reference_paiement')
                    ->label('Référence')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('observations')
                    ->label('Observations')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Encaissé par')
                    ->toggleable()
                    ->visibleFrom('md'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('mode_paiement')
                    ->label('Mode de paiement')
                    ->options(collect(ModePaiement::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()])),

                // Filtre par date
                Tables\Filters\Filter::make('date_paiement')
                    ->form([
                        Forms\Components\DatePicker::make('du')
                            ->label('Du')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('au')
                            ->label('Au')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['du'], fn($q) => $q->whereDate('date_paiement', '>=', $data['du']))
                            ->when($data['au'], fn($q) => $q->whereDate('date_paiement', '<=', $data['au']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // Export CSV des paiements sélectionnés
                Tables\Actions\BulkAction::make('exporter')
                    ->label('Exporter Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        return response()->streamDownload(function () use ($records) {
                            $csv = implode(',', [
                                'N° Reçu', 'Client', 'Date', 'Montant (FCFA)', 'Mode', 'Encaissé par',
                            ]) . "\n";
                            foreach ($records as $paiement) {
                                $csv .= implode(',', [
                                    $paiement->vente?->numero_recu ?? '',
                                    '"' . ($paiement->vente?->client?->nom ?? '') . '"',
                                    $paiement->date_paiement->format('d/m/Y H:i'),
                                    (float) $paiement->montant,
                                    $paiement->mode_paiement?->getLabel() ?? '',
                                    '"' . ($paiement->createdBy?->name ?? '') . '"',
                                ]) . "\n";
                            }
                            echo $csv;
                        }, 'paiements-' . now()->format('Y-m-d') . '.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('Aucun paiement enregistré')
            ->emptyStateDescription('Les paiements apparaissent automatiquement lors des ventes.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->striped();
    }

    /**
     * Pas de création manuelle — les paiements sont créés via les ventes.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaiements::route('/'),
        ];
    }
}
