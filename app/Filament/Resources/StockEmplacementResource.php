<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\CategorieProduit;
use App\Filament\Resources\StockEmplacementResource\Pages;
use App\Models\StockEmplacement;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Resource Filament en LECTURE SEULE pour le stock temps réel.
 *
 * Le stock n'est jamais modifié directement ici — il est mis à jour
 * automatiquement par les productions, ventes, transferts et inventaires
 * via le StockService.
 *
 * Affiche : produit, emplacement, quantité, CMP, valeur, alertes stock bas.
 */
class StockEmplacementResource extends Resource
{
    protected static ?string $model = StockEmplacement::class;

    // Icône "entrepôt" dans le menu de navigation
    protected static ?string $navigationIcon = 'heroicon-o-cube';

    // Groupe de navigation : regroupe les éléments liés au stock
    protected static ?string $navigationGroup = 'Stock';

    protected static ?string $modelLabel = 'Stock';

    protected static ?string $pluralModelLabel = 'Stocks';

    // Ordre d'affichage dans le menu (1 = premier du groupe)
    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('produit.nom')
            ->columns([
                // Nom du produit (via la relation)
                Tables\Columns\TextColumn::make('produit.nom')
                    ->label('Produit')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // Code produit pour identification rapide
                Tables\Columns\TextColumn::make('produit.code')
                    ->label('Code')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->visibleFrom('md'),

                // Catégorie du produit (volaille, découpe, abat, etc.)
                Tables\Columns\TextColumn::make('produit.categorie')
                    ->label('Catégorie')
                    ->badge()
                    ->visibleFrom('md'),

                // Emplacement (Site principal, Belleville)
                Tables\Columns\TextColumn::make('emplacement.nom')
                    ->label('Emplacement')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-map-pin'),

                // Quantité en stock — coloration selon le seuil d'alerte
                Tables\Columns\TextColumn::make('quantite')
                    ->label('Quantité')
                    ->numeric(2)
                    ->sortable()
                    ->color(function (StockEmplacement $record): string {
                        // Rouge si sous le seuil d'alerte du produit
                        $seuil = (float) ($record->produit->seuil_alerte_stock ?? 0);
                        if ($seuil > 0 && (float) $record->quantite <= $seuil) {
                            return 'danger';
                        }
                        // Orange si proche du seuil (< 1.5x le seuil)
                        if ($seuil > 0 && (float) $record->quantite <= $seuil * 1.5) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->weight('bold'),

                // Unité de stock
                Tables\Columns\TextColumn::make('produit.unite_stock')
                    ->label('Unité')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'kg' => 'kg',
                        'unite' => 'unité(s)',
                        'paquet' => 'paquet(s)',
                        default => $state,
                    })
                    ->visibleFrom('md'),

                // CMP = Coût Moyen Pondéré
                Tables\Columns\TextColumn::make('cout_moyen_pondere')
                    ->label('CMP')
                    ->numeric(2)
                    ->suffix(' FCFA')
                    ->sortable()
                    ->toggleable(),

                // Valeur stock = quantité × CMP
                Tables\Columns\TextColumn::make('valeur_stock')
                    ->label('Valeur stock')
                    ->numeric(0)
                    ->suffix(' FCFA')
                    ->sortable()
                    ->weight('bold')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()
                        ->label('Total')
                        ->numeric(0)
                        ->suffix(' FCFA')
                    ),

                // Dernière entrée en stock
                Tables\Columns\TextColumn::make('derniere_entree')
                    ->label('Dernière entrée')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Dernière sortie de stock
                Tables\Columns\TextColumn::make('derniere_sortie')
                    ->label('Dernière sortie')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filtre par emplacement (Site principal, Belleville, etc.)
                Tables\Filters\SelectFilter::make('emplacement_id')
                    ->label('Emplacement')
                    ->relationship('emplacement', 'nom'),

                // Filtre par catégorie de produit
                Tables\Filters\SelectFilter::make('categorie')
                    ->label('Catégorie')
                    ->options(collect(CategorieProduit::cases())->mapWithKeys(
                        fn($e) => [$e->value => $e->getLabel()]
                    ))
                    ->query(function ($query, array $data) {
                        // Filtre via la relation produit.categorie
                        if (filled($data['value'])) {
                            $query->whereHas('produit', fn($q) => $q->where('categorie', $data['value']));
                        }
                    }),

                // Filtre pour n'afficher que les produits en alerte
                Tables\Filters\TernaryFilter::make('alerte_stock')
                    ->label('Alerte stock bas')
                    ->queries(
                        true: fn($query) => $query->whereHas('produit', function ($q) {
                            $q->whereColumn('stock_emplacements.quantite', '<=', 'produits.seuil_alerte_stock')
                                ->where('produits.seuil_alerte_stock', '>', 0);
                        }),
                        false: fn($query) => $query,
                    ),
            ])
            ->actions([
                // Lien vers l'historique des mouvements de ce produit
                Tables\Actions\Action::make('voir_mouvements')
                    ->label('Mouvements')
                    ->icon('heroicon-o-arrow-path')
                    ->url(fn(StockEmplacement $record): string => MouvementStockResource::getUrl('index', [
                        'tableFilters[produit_id][value]' => $record->produit_id,
                        'tableFilters[emplacement_id][value]' => $record->emplacement_id,
                    ]))
                    ->openUrlInNewTab(false),
            ])
            ->bulkActions([])
            // Pas de bouton "Créer" — le stock se crée uniquement via les opérations métier
            ->emptyStateHeading('Aucun stock enregistré')
            ->emptyStateDescription('Le stock se remplit automatiquement lors de la validation des productions.')
            ->emptyStateIcon('heroicon-o-cube')
            ->striped();
    }

    /**
     * Pas de formulaire — le stock est en lecture seule.
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
            'index' => Pages\ListStockEmplacements::route('/'),
        ];
    }
}
