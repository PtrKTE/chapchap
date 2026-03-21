<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Profil;
use App\Traits\WidgetVisibleParProfil;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Tableau widget : Performance des commerciaux ce mois.
 *
 * Affiche pour chaque commercial :
 * - CA du mois (montant_net des ventes non annulées)
 * - Nombre de ventes
 * - Nombre de clients actifs
 * - Montant impayés
 *
 * Trié par CA décroissant.
 *
 * Visible par le Gérant et les Commerciaux (les commerciaux
 * ne voient que leur propre ligne).
 */
class PerformanceCommerciauxTable extends BaseWidget
{
    use WidgetVisibleParProfil;

    protected static ?string $heading = 'Performance commerciaux — mois en cours';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 8;

    public static function canView(): bool
    {
        return static::visiblePour([Profil::GERANT, Profil::RESP_OPERATIONS, Profil::COMMERCIAL]);
    }

    public function table(Table $table): Table
    {
        $debutMois = Carbon::now()->startOfMonth();

        return $table
            ->query(
                User::query()
                    ->where('profil', Profil::COMMERCIAL)
                    ->where('actif', true)
                    // Si l'utilisateur est commercial, ne voir que soi-même
                    ->when(
                        auth()->user()?->profil === Profil::COMMERCIAL,
                        fn(Builder $q) => $q->where('id', auth()->id())
                    )
                    // Ajouter le CA du mois
                    ->withSum(
                        ['ventes as ca_mois' => function ($q) use ($debutMois) {
                            $q->where('annulee', false)
                                ->whereBetween('date_vente', [$debutMois, Carbon::now()]);
                        }],
                        'montant_net'
                    )
                    // Nombre de ventes du mois
                    ->withCount(
                        ['ventes as nb_ventes_mois' => function ($q) use ($debutMois) {
                            $q->where('annulee', false)
                                ->whereBetween('date_vente', [$debutMois, Carbon::now()]);
                        }]
                    )
                    // Montant impayés
                    ->withSum(
                        ['ventes as impayes' => function ($q) {
                            $q->where('annulee', false)
                                ->where('montant_restant', '>', 0);
                        }],
                        'montant_restant'
                    )
                    ->orderByDesc('ca_mois')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Commercial')
                    ->weight('bold')
                    ->icon('heroicon-m-user'),

                Tables\Columns\TextColumn::make('ca_mois')
                    ->label('CA mois')
                    ->formatStateUsing(fn($state) => number_format((float) ($state ?? 0), 0, ',', ' ') . ' F')
                    ->color('success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('nb_ventes_mois')
                    ->label('Nb ventes')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('clients_count')
                    ->label('Clients actifs')
                    ->getStateUsing(function (User $record): int {
                        return $record->clients()->where('actif', true)->count();
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('impayes')
                    ->label('Impayés')
                    ->formatStateUsing(fn($state) => number_format((float) ($state ?? 0), 0, ',', ' ') . ' F')
                    ->color(fn($state) => ((float) ($state ?? 0)) > 0 ? 'danger' : 'success'),
            ])
            ->paginated(false)
            ->striped();
    }
}
