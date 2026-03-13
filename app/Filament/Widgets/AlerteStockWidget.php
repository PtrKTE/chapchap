<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Profil;
use App\Models\StockEmplacement;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Widget dashboard : Alertes de stock bas.
 *
 * Affiche les produits dont la quantité en stock est inférieure
 * ou égale au seuil d'alerte défini dans la fiche produit.
 *
 * Visible sur le dashboard principal — permet au gérant et au
 * gestionnaire stock de voir immédiatement les réapprovisionnements nécessaires.
 */
class AlerteStockWidget extends BaseWidget
{
    // Titre du widget affiché en haut
    protected static ?string $heading = 'Alertes stock bas';

    // Largeur du widget : occupe toute la largeur
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 10;

    public static function canView(): bool
    {
        $profil = auth()->user()?->profil;

        return in_array($profil, [
            Profil::GERANT,
            Profil::RESP_OPERATIONS,
            Profil::GESTIONNAIRE_STOCK,
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // On cherche les produits dont le stock est sous le seuil d'alerte
                StockEmplacement::query()
                    ->whereHas('produit', function ($q) {
                        // Seuil > 0 ET quantité en stock <= seuil
                        $q->where('seuil_alerte_stock', '>', 0);
                    })
                    ->whereColumn('quantite', '<=', 'produits.seuil_alerte_stock')
                    ->join('produits', 'stock_emplacements.produit_id', '=', 'produits.id')
                    ->select('stock_emplacements.*')
            )
            ->columns([
                Tables\Columns\TextColumn::make('produit.nom')
                    ->label('Produit')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('produit.code')
                    ->label('Code')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('emplacement.nom')
                    ->label('Emplacement'),

                Tables\Columns\TextColumn::make('quantite')
                    ->label('Stock actuel')
                    ->numeric(2)
                    ->color('danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('produit.seuil_alerte_stock')
                    ->label('Seuil alerte')
                    ->numeric(2)
                    ->color('warning'),
            ])
            ->emptyStateHeading('Aucune alerte')
            ->emptyStateDescription('Tous les produits sont au-dessus de leur seuil de stock minimum.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated(false)
            ->striped();
    }
}
