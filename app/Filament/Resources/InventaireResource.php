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
                            Infolists\Components\TextEntry::make('ecart')->label('Écart')->numeric(3)
                                ->color(fn($state) => (float) $state == 0 ? 'success' : ((float) $state > 0 ? 'info' : 'danger')),
                            Infolists\Components\TextEntry::make('justification')->label('Justification')->placeholder('—'),
                        ])->columns(5),
                ]),

            Infolists\Components\Section::make('Validation')
                ->schema([
                    Infolists\Components\TextEntry::make('createdBy.name')->label('Créé par')->placeholder('—'),
                    Infolists\Components\TextEntry::make('validePar.name')->label('Validé par')->placeholder('—'),
                    Infolists\Components\TextEntry::make('observations')->label('Observations')->placeholder('—')->columnSpanFull(),
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
                ->description('Saisissez la quantité physique comptée pour chaque produit. Le stock théorique est rempli automatiquement.')
                ->schema([
                    Forms\Components\Repeater::make('lignes')
                        ->label('')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('produit_id')
                                ->label('Produit')
                                ->options(
                                    Produit::where('actif', true)->pluck('nom', 'id')
                                )
                                ->required()
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                    // Auto-remplir le stock théorique quand on sélectionne un produit
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

                            // Stock théorique = ce que dit le système
                            Forms\Components\TextInput::make('stock_theorique')
                                ->label('Stock théorique')
                                ->numeric()
                                ->step(0.001)
                                ->disabled()
                                ->dehydrated(true)
                                ->helperText('Quantité système'),

                            // Stock physique = ce qu'on a compté
                            Forms\Components\TextInput::make('stock_physique')
                                ->label('Stock physique')
                                ->numeric()
                                ->required()
                                ->step(0.001)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    // Calcul automatique de l'écart
                                    $theorique = (float) ($get('stock_theorique') ?? 0);
                                    $physique = (float) ($get('stock_physique') ?? 0);
                                    $set('ecart', round($physique - $theorique, 3));
                                })
                                ->helperText('Quantité comptée'),

                            // Écart = physique - théorique (calculé auto)
                            Forms\Components\TextInput::make('ecart')
                                ->label('Écart')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(true)
                                ->helperText('Physique - Théorique'),

                            // Justification obligatoire si écart
                            Forms\Components\TextInput::make('justification')
                                ->label('Justification')
                                ->helperText('Obligatoire si écart')
                                ->columnSpanFull(),
                        ])
                        ->columns(4)
                        ->defaultItems(0)
                        ->addActionLabel('Ajouter un produit')
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
            'index' => Pages\ListInventaires::route('/'),
            'create' => Pages\CreateInventaire::route('/create'),
            'edit' => Pages\EditInventaire::route('/{record}/edit'),
        ];
    }
}
