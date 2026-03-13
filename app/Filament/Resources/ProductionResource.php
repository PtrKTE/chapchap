<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\StatutProduction;
use App\Filament\Resources\ProductionResource\Pages;
use App\Models\Lot;
use App\Models\Production;
use App\Models\Produit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductionResource extends Resource
{
    protected static ?string $model = Production::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Production';

    protected static ?string $modelLabel = 'Production';

    protected static ?string $pluralModelLabel = 'Productions';

    protected static ?int $navigationSort = 1;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Informations')
                ->icon('heroicon-o-cog-6-tooth')
                ->schema([
                    Infolists\Components\TextEntry::make('reference')->label('Référence')->weight('bold')->copyable(),
                    Infolists\Components\TextEntry::make('date_production')->label('Date')->date('d/m/Y'),
                    Infolists\Components\TextEntry::make('lot.reference')->label('Lot source')->icon('heroicon-o-archive-box'),
                    Infolists\Components\TextEntry::make('statut')->label('Statut')->badge(),
                ])->columns(4),

            Infolists\Components\Section::make('Volumes')
                ->icon('heroicon-o-scale')
                ->schema([
                    Infolists\Components\TextEntry::make('nb_poulets_traites')->label('Poulets traités')->numeric(0)->weight('bold'),
                    Infolists\Components\TextEntry::make('poids_moyen_entrant')->label('Poids moyen')->numeric(3)->suffix(' kg'),
                    Infolists\Components\TextEntry::make('poids_total_entrant')->label('Poids total')->numeric(1)->suffix(' kg'),
                    Infolists\Components\TextEntry::make('pertes_casse')->label('Pertes/casse')->numeric(1)->suffix(' kg')->color('danger'),
                    Infolists\Components\TextEntry::make('rendement')->label('Rendement')
                        ->formatStateUsing(fn($state) => $state ? number_format((float) $state * 100, 1) . ' %' : '—')
                        ->color(fn($state) => $state && (float) $state > 0.8 ? 'success' : 'warning'),
                ])->columns(5),

            Infolists\Components\Section::make('Produits obtenus')
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('lignes')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('produit.nom')->label('Produit')->weight('bold'),
                            Infolists\Components\TextEntry::make('quantite')->label('Quantité')->numeric(3),
                            Infolists\Components\TextEntry::make('cout_unitaire_calcule')->label('Coût unit.')->numeric(2)->suffix(' FCFA'),
                            Infolists\Components\TextEntry::make('valeur_totale')->label('Valeur')->numeric(0)->suffix(' FCFA')->color('primary'),
                        ])->columns(4),
                ]),

            Infolists\Components\Section::make('Validation')
                ->schema([
                    Infolists\Components\TextEntry::make('validePar.name')->label('Validé par')->placeholder('—'),
                    Infolists\Components\TextEntry::make('date_validation')->label('Date validation')->dateTime('d/m/Y H:i')->placeholder('—'),
                    Infolists\Components\TextEntry::make('observations')->label('Observations')->placeholder('—')->columnSpanFull(),
                ])->columns(2)
                ->collapsible(),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Section 1 : Informations générales
            Forms\Components\Section::make('Informations de production')
                ->description('Lot source et paramètres de la production')
                ->schema([
                    Forms\Components\TextInput::make('reference')
                        ->label('Référence')
                        ->disabled()
                        ->dehydrated(false)
                        ->hiddenOn('create')
                        ->helperText('Générée automatiquement'),
                    Forms\Components\DatePicker::make('date_production')
                        ->label('Date de production')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    Forms\Components\Select::make('lot_id')
                        ->label('Lot source')
                        ->options(function () {
                            // Lots avec encore des poulets disponibles
                            return Lot::where('type_produit', 'poulet')
                                ->where('quantite_utilisable', '>', 0)
                                ->get()
                                ->mapWithKeys(fn(Lot $lot) => [
                                    $lot->id => "{$lot->reference} — {$lot->quantite_utilisable} poulets dispo (CMP: " . number_format((float) $lot->cout_moyen_unitaire, 0, ',', ' ') . " FCFA)",
                                ]);
                        })
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                            if ($state) {
                                $lot = Lot::find($state);
                                if ($lot) {
                                    // Pré-remplir le nombre de poulets traités
                                    $set('nb_poulets_traites', $lot->quantite_utilisable);
                                }
                            }
                        }),
                    Forms\Components\Placeholder::make('lot_info')
                        ->label('Info lot')
                        ->content(function (Get $get): string {
                            $lotId = $get('lot_id');
                            if (! $lotId) {
                                return 'Sélectionnez un lot';
                            }
                            $lot = Lot::find($lotId);
                            if (! $lot) {
                                return '—';
                            }

                            return "Fournisseur : {$lot->fournisseur->nom} | Reçu : {$lot->quantite_recue} | Utilisable : {$lot->quantite_utilisable} | CMP : " . number_format((float) $lot->cout_moyen_unitaire, 2, ',', ' ') . ' FCFA';
                        })
                        ->columnSpanFull(),
                ])->columns(2),

            // Section 2 : Volumes
            Forms\Components\Section::make('Volumes traités')
                ->schema([
                    Forms\Components\TextInput::make('nb_poulets_traites')
                        ->label('Nombre de poulets traités')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->live(onBlur: true)
                        ->rules([
                            function (Get $get) {
                                return function (string $attribute, $value, $fail) use ($get) {
                                    $lotId = $get('lot_id');
                                    if ($lotId) {
                                        $lot = Lot::find($lotId);
                                        if ($lot && (int) $value > $lot->quantite_utilisable) {
                                            $fail("Impossible de traiter plus que la quantité utilisable du lot ({$lot->quantite_utilisable}).");
                                        }
                                    }
                                };
                            },
                        ]),
                    Forms\Components\TextInput::make('poids_moyen_entrant')
                        ->label('Poids moyen entrant (kg)')
                        ->numeric()
                        ->step(0.001)
                        ->suffix('kg')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            $nb = (int) ($get('nb_poulets_traites') ?? 0);
                            $poidsMoyen = (float) ($get('poids_moyen_entrant') ?? 0);
                            $set('poids_total_entrant', round($nb * $poidsMoyen, 3));
                        }),
                    Forms\Components\TextInput::make('poids_total_entrant')
                        ->label('Poids total entrant (kg)')
                        ->numeric()
                        ->step(0.001)
                        ->suffix('kg'),
                    Forms\Components\TextInput::make('pertes_casse')
                        ->label('Pertes / casse (kg)')
                        ->numeric()
                        ->default(0)
                        ->step(0.001)
                        ->suffix('kg'),
                ])->columns(2),

            // Section 3 : Lignes de production (Repeater)
            Forms\Components\Section::make('Produits obtenus')
                ->description('Détail des produits issus de la découpe / abattage')
                ->schema([
                    Forms\Components\Repeater::make('lignes')
                        ->label('')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('produit_id')
                                ->label('Produit')
                                ->options(
                                    Produit::where('actif', true)
                                        ->whereIn('categorie', ['decoupe', 'abat', 'volaille'])
                                        ->pluck('nom', 'id')
                                )
                                ->required()
                                ->searchable(),
                            Forms\Components\TextInput::make('quantite')
                                ->label('Quantité (kg ou unités)')
                                ->numeric()
                                ->required()
                                ->minValue(0.001)
                                ->step(0.001),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Ajouter un produit')
                        ->reorderable(false)
                        ->collapsible()
                        ->itemLabel(fn(array $state): ?string => $state['produit_id']
                            ? Produit::find($state['produit_id'])?->nom ?? 'Produit'
                            : 'Nouveau produit'
                        ),
                ]),

            // Section 4 : Statut et observations
            Forms\Components\Section::make('Statut & Observations')
                ->schema([
                    Forms\Components\Select::make('statut')
                        ->label('Statut')
                        ->options(collect(StatutProduction::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()]))
                        ->default('en_cours')
                        ->required()
                        ->disabled(fn(?Production $record): bool => $record?->statut === StatutProduction::VALIDEE),
                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with(['lot', 'validePar']))
            ->defaultSort('date_production', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('date_production')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('lot.reference')
                    ->label('Lot source')
                    ->searchable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('nb_poulets_traites')
                    ->label('Poulets traités')
                    ->numeric(0)
                    ->sortable(),
                Tables\Columns\TextColumn::make('poids_total_entrant')
                    ->label('Poids total (kg)')
                    ->numeric(1)
                    ->suffix(' kg')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pertes_casse')
                    ->label('Pertes (kg)')
                    ->numeric(1)
                    ->suffix(' kg')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('rendement')
                    ->label('Rendement')
                    ->formatStateUsing(fn($state) => $state ? number_format((float) $state * 100, 1) . ' %' : '—')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                Tables\Columns\TextColumn::make('validePar.name')
                    ->label('Validé par')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(collect(StatutProduction::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()])),
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
            'index' => Pages\ListProductions::route('/'),
            'create' => Pages\CreateProduction::route('/create'),
            'edit' => Pages\EditProduction::route('/{record}/edit'),
        ];
    }
}
