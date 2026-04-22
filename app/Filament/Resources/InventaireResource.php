<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\StatutInventaire;
use App\Filament\Resources\InventaireResource\Pages;
use App\Models\Emplacement;
use App\Models\Inventaire;
use App\Models\Produit;
use App\Models\StockEmplacement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Resource Filament pour les inventaires physiques.
 */
class InventaireResource extends Resource
{
    protected static ?string $model = Inventaire::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Stock';

    protected static ?string $modelLabel = 'Inventaire';

    protected static ?string $pluralModelLabel = 'Inventaires';

    protected static ?int $navigationSort = 4;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Informations')
                ->icon('heroicon-o-clipboard-document-check')
                ->schema([
                    Infolists\Components\TextEntry::make('reference')->label('Référence')->weight('bold')->copyable(),
                    Infolists\Components\TextEntry::make('date_inventaire')->label('Date')->date('d/m/Y'),
                    Infolists\Components\TextEntry::make('emplacement.nom')->label('Emplacement')->icon('heroicon-o-map-pin'),
                    Infolists\Components\TextEntry::make('statut')->label('Statut')->badge(),
                ])->columns(4),

            Infolists\Components\Section::make('Lignes d\'inventaire')
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('lignes')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('produit.nom')->label('Produit')->weight('bold'),
                            Infolists\Components\TextEntry::make('stock_theorique')->label('Théorique')->numeric(3),
                            Infolists\Components\TextEntry::make('stock_physique')->label('Physique')->numeric(3),
                            Infolists\Components\TextEntry::make('ecart_calcule')
                                ->label('Écart')
                                ->getStateUsing(fn($record) => round((float) $record->stock_physique - (float) $record->stock_theorique, 3))
                                ->numeric(3)
                                ->color(fn($state) => (float) $state == 0 ? 'success' : ((float) $state > 0 ? 'info' : 'danger')),
                        ])->columns(5),
                ]),

            // Résumé des ajustements — visible uniquement après validation
            Infolists\Components\Section::make('Résumé des ajustements')
                ->icon('heroicon-o-chart-bar')
                ->visible(fn(Inventaire $record): bool => $record->statut === StatutInventaire::VALIDE)
                ->schema([
                    Infolists\Components\TextEntry::make('nb_avec_ecart')
                        ->label('Produits avec écart')
                        ->getStateUsing(fn(Inventaire $record): string =>
                            $record->lignes->filter(fn($l) => abs((float) $l->stock_physique - (float) $l->stock_theorique) > 0.001)->count()
                            . ' / ' . $record->lignes->count()
                        ),
                    Infolists\Components\TextEntry::make('valeur_pertes')
                        ->label('Valeur des pertes (−)')
                        ->getStateUsing(function (Inventaire $record): string {
                            $total = 0;
                            foreach ($record->lignes as $ligne) {
                                $ecart = round((float) $ligne->stock_physique - (float) $ligne->stock_theorique, 3);
                                if ($ecart >= 0) continue;
                                $cmp = (float) StockEmplacement::where('produit_id', $ligne->produit_id)
                                    ->where('emplacement_id', $record->emplacement_id)
                                    ->value('cout_moyen_pondere');
                                $total += abs($ecart) * $cmp;
                            }
                            return number_format($total, 0, ',', ' ') . ' FCFA';
                        })
                        ->color('danger'),
                    Infolists\Components\TextEntry::make('valeur_gains')
                        ->label('Valeur des gains (+)')
                        ->getStateUsing(function (Inventaire $record): string {
                            $total = 0;
                            foreach ($record->lignes as $ligne) {
                                $ecart = round((float) $ligne->stock_physique - (float) $ligne->stock_theorique, 3);
                                if ($ecart <= 0) continue;
                                $cmp = (float) StockEmplacement::where('produit_id', $ligne->produit_id)
                                    ->where('emplacement_id', $record->emplacement_id)
                                    ->value('cout_moyen_pondere');
                                $total += $ecart * $cmp;
                            }
                            return number_format($total, 0, ',', ' ') . ' FCFA';
                        })
                        ->color('success'),
                    Infolists\Components\TextEntry::make('impact_net')
                        ->label('Impact net sur le stock')
                        ->getStateUsing(function (Inventaire $record): string {
                            $net = 0;
                            foreach ($record->lignes as $ligne) {
                                $ecart = round((float) $ligne->stock_physique - (float) $ligne->stock_theorique, 3);
                                if (abs($ecart) < 0.001) continue;
                                $cmp = (float) StockEmplacement::where('produit_id', $ligne->produit_id)
                                    ->where('emplacement_id', $record->emplacement_id)
                                    ->value('cout_moyen_pondere');
                                $net += $ecart * $cmp;
                            }
                            $signe = $net >= 0 ? '+' : '';
                            return $signe . number_format($net, 0, ',', ' ') . ' FCFA';
                        })
                        ->color(fn(Inventaire $record): string => $record->lignes->sum(fn($l) => (float) $l->stock_physique - (float) $l->stock_theorique) >= 0 ? 'success' : 'danger')
                        ->weight('bold'),
                ])->columns(4),

            Infolists\Components\Section::make('Validation')
                ->schema([
                ])->columns(2)
                ->collapsible(),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Section 1 : Informations de l'inventaire
            Forms\Components\Section::make('Informations de l\'inventaire')
                ->description('Emplacement et date de l\'inventaire physique')
                ->schema([
                    Forms\Components\TextInput::make('reference')
                        ->label('Référence')
                        ->disabled()
                        ->dehydrated(false)
                        ->hiddenOn('create')
                        ->helperText('Générée automatiquement'),

                    Forms\Components\DatePicker::make('date_inventaire')
                        ->label('Date de l\'inventaire')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    Forms\Components\Select::make('emplacement_id')
                        ->label('Emplacement')
                        ->options(Emplacement::where('actif', true)->pluck('nom', 'id'))
                        ->required()
                        ->live()
                        ->helperText('Sélectionnez l\'emplacement à inventorier'),

                    Forms\Components\Select::make('statut')
                        ->label('Statut')
                        ->options(collect(StatutInventaire::cases())->mapWithKeys(fn ($e) => [$e->value => $e->getLabel()]))
                        ->default(StatutInventaire::EN_COURS->value)
                        ->required()
                        ->disabled(),
                ])->columns(2),

            // Section 2 : Lignes d'inventaire
            Forms\Components\Section::make('Comptage des produits')
                ->description('Cliquez sur "Charger tous les produits" pour pré-remplir le stock théorique, puis saisissez les quantités physiques comptées.')
                ->schema([
                    // Bouton pour charger automatiquement tous les produits en stock
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('charger_produits')
                            ->label('Charger tous les produits en stock')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->color('info')
                            ->action(function (Get $get, Set $set) {
                                $emplacementId = $get('emplacement_id');

                                if (! $emplacementId) {
                                    \Filament\Notifications\Notification::make()
                                        ->warning()
                                        ->title('Sélectionnez d\'abord un emplacement.')
                                        ->send();
                                    return;
                                }

                                // Récupérer tous les produits avec stock > 0 pour cet emplacement
                                $stocks = StockEmplacement::where('emplacement_id', $emplacementId)
                                    ->where('quantite', '>', 0)
                                    ->with('produit')
                                    ->get();

                                if ($stocks->isEmpty()) {
                                    \Filament\Notifications\Notification::make()
                                        ->warning()
                                        ->title('Aucun produit en stock pour cet emplacement.')
                                        ->send();
                                    return;
                                }

                                // Remplir le Repeater : stock théorique = stock système, physique vide
                                $lignes = $stocks->map(fn($stock) => [
                                    'produit_id'      => $stock->produit_id,
                                    'stock_theorique' => (float) $stock->quantite,
                                    'stock_physique'  => null,
                                    'ecart'           => 0,
                                    'justification'   => null,
                                ])->values()->toArray();

                                $set('lignes', $lignes);

                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title("{$stocks->count()} produits chargés. Saisissez les quantités comptées.")
                                    ->send();
                            }),
                    ]),

                    Forms\Components\Repeater::make('lignes')
                        ->label('')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('produit_id')
                                ->label('Produit')
                                ->options(Produit::where('actif', true)->pluck('nom', 'id'))
                                ->required()
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                    // Auto-remplir le stock théorique quand on sélectionne un produit manuellement
                                    if ($state) {
                                        $emplacementId = $get('../../emplacement_id');
                                        if ($emplacementId) {
                                            $stock = StockEmplacement::where('produit_id', $state)
                                                ->where('emplacement_id', $emplacementId)
                                                ->first();
                                            $set('stock_theorique', $stock ? (float) $stock->quantite : 0);
                                        }
                                    }
                                }),

                            // Stock théorique = ce que dit le système (non modifiable)
                            Forms\Components\TextInput::make('stock_theorique')
                                ->label('Stock théorique')
                                ->numeric()
                                ->step(0.001)
                                ->disabled()
                                ->dehydrated(true)
                                ->helperText('Quantité système'),

                            // Stock physique = ce qu'on a compté réellement
                            Forms\Components\TextInput::make('stock_physique')
                                ->label('Stock physique compté')
                                ->numeric()
                                ->required()
                                ->step(0.001)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $theorique = (float) ($get('stock_theorique') ?? 0);
                                    $physique  = (float) ($get('stock_physique') ?? 0);
                                    $set('ecart', round($physique - $theorique, 3));
                                })
                                ->helperText('Quantité comptée sur le terrain'),

                            // Champ ecart caché — sauvegardé en base
                            Forms\Components\TextInput::make('ecart')
                                ->label('Écart')
                                ->numeric()
                                ->hidden()
                                ->dehydrated(true),

                            // Affichage coloré de l'écart (physique - théorique)
                            Forms\Components\Placeholder::make('ecart_display')
                                ->label('Écart')
                                ->content(function (Get $get): HtmlString {
                                    $ecart = (float) ($get('ecart') ?? 0);
                                    if (abs($ecart) < 0.001) {
                                        return new HtmlString(
                                            '<span style="color:#16a34a;font-weight:bold;">✓ 0</span>'
                                        );
                                    }
                                    if ($ecart > 0) {
                                        return new HtmlString(
                                            '<span style="color:#2563eb;font-weight:bold;">▲ +' . number_format($ecart, 3, ',', ' ') . '</span>'
                                        );
                                    }
                                    return new HtmlString(
                                        '<span style="color:#dc2626;font-weight:bold;">▼ ' . number_format($ecart, 3, ',', ' ') . '</span>'
                                    );
                                })
                                ->dehydrated(false),

                            // Justification obligatoire si écart
                            Forms\Components\TextInput::make('justification')
                                ->label('Justification')
                                ->helperText('Obligatoire si écart ≠ 0')
                                ->columnSpanFull(),
                        ])
                        ->columns(5)
                        ->defaultItems(0)
                        ->addActionLabel('Ajouter un produit manuellement')
                        ->reorderable(false)
                        ->collapsible()
                        ->itemLabel(fn(array $state): ?string => $state['produit_id']
                            ? Produit::find($state['produit_id'])?->nom ?? 'Produit'
                            : 'Nouveau produit'
                        ),
                ]),

            // Section 3 : Observations
            Forms\Components\Section::make('Observations')
                ->schema([
                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with(['emplacement', 'createdBy', 'validePar']))
            ->defaultSort('date_inventaire', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('date_inventaire')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('emplacement.nom')
                    ->label('Emplacement')
                    ->sortable(),

                // Nombre de produits inventoriés
                Tables\Columns\TextColumn::make('lignes_count')
                    ->label('Nb produits')
                    ->counts('lignes')
                    ->sortable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),

                Tables\Columns\TextColumn::make('createdBy.nom')
                    ->label('Créé par')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('validePar.nom')
                    ->label('Validé par')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('emplacement_id')
                    ->label('Emplacement')
                    ->relationship('emplacement', 'nom'),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(collect(StatutInventaire::cases())->mapWithKeys(fn ($e) => [$e->value => $e->getLabel()])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListInventaires::route('/'),
            'create' => Pages\CreateInventaire::route('/create'),
            'view'   => Pages\ViewInventaire::route('/{record}'),
            'edit'   => Pages\EditInventaire::route('/{record}/edit'),
        ];
    }
}
