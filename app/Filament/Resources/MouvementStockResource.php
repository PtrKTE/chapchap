<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\TypeMouvement;
use App\Filament\Resources\MouvementStockResource\Pages;
use App\Models\MouvementStock;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Resource Filament en LECTURE SEULE pour les mouvements de stock.
 */
class MouvementStockResource extends Resource
{
    protected static ?string $model = MouvementStock::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Stock';

    protected static ?string $modelLabel = 'Mouvement';

    protected static ?string $pluralModelLabel = 'Mouvements de stock';

    protected static ?int $navigationSort = 2;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Détails du mouvement')
                ->icon('heroicon-o-arrow-path')
                ->schema([
                    Infolists\Components\TextEntry::make('reference')->label('Référence')->weight('bold')->copyable(),
                    Infolists\Components\TextEntry::make('date_mouvement')->label('Date')->dateTime('d/m/Y H:i'),
                    Infolists\Components\TextEntry::make('type_mouvement')->label('Type')->badge()
                        ->color(fn(TypeMouvement $state) => $state->isEntree() ? 'success' : 'danger'),
                    Infolists\Components\TextEntry::make('produit.nom')->label('Produit')->weight('bold'),
                    Infolists\Components\TextEntry::make('emplacement.nom')->label('Emplacement')->icon('heroicon-o-map-pin'),
                    Infolists\Components\TextEntry::make('quantite')->label('Quantité')->numeric(2)
                        ->prefix(fn(MouvementStock $record) => $record->type_mouvement->isEntree() ? '+' : '-')
                        ->color(fn(MouvementStock $record) => $record->type_mouvement->isEntree() ? 'success' : 'danger'),
                    Infolists\Components\TextEntry::make('cout_unitaire')->label('Coût unitaire')->numeric(2)->suffix(' FCFA'),
                    Infolists\Components\TextEntry::make('valeur')->label('Valeur totale')->numeric(0)->suffix(' FCFA')->weight('bold'),
                ])->columns(4),

            Infolists\Components\Section::make('Traçabilité')
                ->schema([
                ])->columns(4)
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with(['produit', 'emplacement', 'lot', 'production', 'transfert', 'createdBy']))
            ->defaultSort('date_mouvement', 'desc')
            ->columns([
                // Référence unique du mouvement (MVT-YYYYMMDD-XXXX)
                Tables\Columns\TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // Date et heure du mouvement
                Tables\Columns\TextColumn::make('date_mouvement')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->visibleFrom('md'),

                // Type de mouvement avec badge coloré
                Tables\Columns\TextColumn::make('type_mouvement')
                    ->label('Type')
                    ->badge()
                    ->color(fn(TypeMouvement $state): string => match (true) {
                        $state->isEntree() => 'success',  // Vert pour les entrées
                        default => 'danger',                // Rouge pour les sorties
                    }),

                // Produit concerné
                Tables\Columns\TextColumn::make('produit.nom')
                    ->label('Produit')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('produit.code')
                    ->label('Code')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Emplacement (Site principal ou Belleville)
                Tables\Columns\TextColumn::make('emplacement.nom')
                    ->label('Emplacement')
                    ->sortable()
                    ->visibleFrom('md'),

                // Quantité mouvementée (toujours positive, le type indique le sens)
                Tables\Columns\TextColumn::make('quantite')
                    ->label('Quantité')
                    ->numeric(2)
                    ->sortable()
                    ->color(fn(MouvementStock $record): string =>
                        $record->type_mouvement->isEntree() ? 'success' : 'danger'
                    )
                    ->prefix(fn(MouvementStock $record): string =>
                        $record->type_mouvement->isEntree() ? '+' : '-'
                    ),

                // Coût unitaire (CMP) au moment du mouvement
                Tables\Columns\TextColumn::make('cout_unitaire')
                    ->label('Coût unit.')
                    ->numeric(2)
                    ->suffix(' FCFA')
                    ->toggleable(),

                // Valeur totale du mouvement (quantité × coût unitaire)
                Tables\Columns\TextColumn::make('valeur')
                    ->label('Valeur')
                    ->numeric(0)
                    ->suffix(' FCFA')
                    ->sortable()
                    ->visibleFrom('md'),

                // Lot source (si le mouvement vient d'un achat ou production)
                Tables\Columns\TextColumn::make('lot.reference')
                    ->label('Lot')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Production associée
                Tables\Columns\TextColumn::make('production.reference')
                    ->label('Production')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Transfert associé
                Tables\Columns\TextColumn::make('transfert.reference')
                    ->label('Transfert')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Motif (pour les ajustements et pertes)
                Tables\Columns\TextColumn::make('motif')
                    ->label('Motif')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                // Utilisateur qui a déclenché le mouvement
                Tables\Columns\TextColumn::make('createdBy.nom')
                    ->label('Par')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filtre par type de mouvement
                Tables\Filters\SelectFilter::make('type_mouvement')
                    ->label('Type')
                    ->options(collect(TypeMouvement::cases())->mapWithKeys(
                        fn($e) => [$e->value => $e->getLabel()]
                    )),

                // Filtre par produit
                Tables\Filters\SelectFilter::make('produit_id')
                    ->label('Produit')
                    ->relationship('produit', 'nom')
                    ->searchable()
                    ->preload(),

                // Filtre par emplacement
                Tables\Filters\SelectFilter::make('emplacement_id')
                    ->label('Emplacement')
                    ->relationship('emplacement', 'nom'),

                // Filtre par date
                Tables\Filters\Filter::make('date_mouvement')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('du')
                            ->label('Du')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        \Filament\Forms\Components\DatePicker::make('au')
                            ->label('Au')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['du'], fn($q) => $q->whereDate('date_mouvement', '>=', $data['du']))
                            ->when($data['au'], fn($q) => $q->whereDate('date_mouvement', '<=', $data['au']));
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['du'] && $data['au']) {
                            return 'Période : ' . $data['du'] . ' → ' . $data['au'];
                        }

                        return null;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Aucun mouvement de stock')
            ->emptyStateDescription('Les mouvements apparaissent automatiquement lors des opérations de production, vente et transfert.')
            ->emptyStateIcon('heroicon-o-arrow-path')
            ->striped();
    }

    /**
     * Pas de création manuelle — les mouvements sont générés automatiquement.
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
            'index' => Pages\ListMouvementStocks::route('/'),
        ];
    }
}
