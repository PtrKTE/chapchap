<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\StatutTransfert;
use App\Filament\Resources\TransfertResource\Pages;
use App\Models\Emplacement;
use App\Models\Produit;
use App\Models\StockEmplacement;
use App\Models\Transfert;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Resource Filament pour les transferts inter-sites.
 */
class TransfertResource extends Resource
{
    protected static ?string $model = Transfert::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Stock';

    protected static ?string $modelLabel = 'Transfert';

    protected static ?string $pluralModelLabel = 'Transferts';

    protected static ?int $navigationSort = 3;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Informations du transfert')
                ->icon('heroicon-o-truck')
                ->schema([
                    Infolists\Components\TextEntry::make('reference')->label('Référence')->weight('bold')->copyable(),
                    Infolists\Components\TextEntry::make('date_transfert')->label('Date')->dateTime('d/m/Y H:i'),
                    Infolists\Components\TextEntry::make('emplacementSource.nom')->label('Source')->icon('heroicon-o-arrow-up-tray'),
                    Infolists\Components\TextEntry::make('emplacementDest.nom')->label('Destination')->icon('heroicon-o-arrow-down-tray'),
                    Infolists\Components\TextEntry::make('statut')->label('Statut')->badge(),
                    Infolists\Components\TextEntry::make('createdBy.name')->label('Créé par')->placeholder('—'),
                ])->columns(3),

            Infolists\Components\Section::make('Produits transférés')
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('lignes')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('produit.nom')->label('Produit')->weight('bold'),
                            Infolists\Components\TextEntry::make('quantite_envoyee')->label('Qté envoyée')->numeric(3),
                            Infolists\Components\TextEntry::make('quantite_recue')->label('Qté reçue')->numeric(3)->placeholder('—'),
                            Infolists\Components\TextEntry::make('ecart')->label('Écart')->numeric(3)
                                ->color(fn($state) => $state && (float) $state != 0 ? 'danger' : 'success')
                                ->placeholder('—'),
                        ])->columns(4),
                ]),

            Infolists\Components\Section::make('Réception')
                ->schema([
                    Infolists\Components\TextEntry::make('recuPar.name')->label('Reçu par')->placeholder('—'),
                    Infolists\Components\TextEntry::make('date_reception')->label('Date réception')->dateTime('d/m/Y H:i')->placeholder('—'),
                    Infolists\Components\TextEntry::make('observations')->label('Observations')->placeholder('—')->columnSpanFull(),
                ])->columns(2)
                ->collapsible(),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Section 1 : Informations du transfert
            Forms\Components\Section::make('Informations du transfert')
                ->description('Source, destination et date du transfert')
                ->schema([
                    Forms\Components\TextInput::make('reference')
                        ->label('Référence')
                        ->disabled()
                        ->dehydrated(false)
                        ->hiddenOn('create')
                        ->helperText('Générée automatiquement'),

                    Forms\Components\DateTimePicker::make('date_transfert')
                        ->label('Date du transfert')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y H:i'),

                    // Emplacement source (d'où partent les produits)
                    Forms\Components\Select::make('emplacement_source_id')
                        ->label('Emplacement source')
                        ->options(Emplacement::where('actif', true)->pluck('nom', 'id'))
                        ->required()
                        ->live()
                        ->different('emplacement_dest_id')
                        ->helperText('D\'où partent les produits'),

                    // Emplacement destination (où arrivent les produits)
                    Forms\Components\Select::make('emplacement_dest_id')
                        ->label('Emplacement destination')
                        ->options(Emplacement::where('actif', true)->pluck('nom', 'id'))
                        ->required()
                        ->live()
                        ->different('emplacement_source_id')
                        ->helperText('Où arrivent les produits'),
                ])->columns(2),

            // Section 2 : Lignes du transfert (les produits transférés)
            Forms\Components\Section::make('Produits à transférer')
                ->description('Ajoutez les produits et quantités à envoyer')
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
                                ->afterStateUpdated(function (Get $get, Forms\Components\Select $component) {
                                    // Remettre à zéro la quantité quand on change de produit
                                }),

                            Forms\Components\TextInput::make('quantite_envoyee')
                                ->label('Quantité envoyée')
                                ->numeric()
                                ->required()
                                ->minValue(0.001)
                                ->step(0.001),

                            // Quantité reçue : remplie lors de la réception
                            Forms\Components\TextInput::make('quantite_recue')
                                ->label('Quantité reçue')
                                ->numeric()
                                ->step(0.001)
                                ->placeholder('À remplir à la réception')
                                ->helperText('Renseigné lors de la réception'),
                        ])
                        ->columns(3)
                        ->defaultItems(1)
                        ->addActionLabel('Ajouter un produit')
                        ->reorderable(false)
                        ->collapsible()
                        ->itemLabel(fn(array $state): ?string => $state['produit_id']
                            ? Produit::find($state['produit_id'])?->nom ?? 'Produit'
                            : 'Nouveau produit'
                        ),
                ]),

            // Section 3 : Observations
            Forms\Components\Section::make('Statut & Observations')
                ->schema([
                    Forms\Components\Select::make('statut')
                        ->label('Statut')
                        ->options(collect(StatutTransfert::cases())->mapWithKeys(
                            fn($e) => [$e->value => $e->getLabel()]
                        ))
                        ->default('en_cours')
                        ->required()
                        ->disabled(),

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
            ->modifyQueryUsing(fn($query) => $query->with(['emplacementSource', 'emplacementDest', 'createdBy', 'recuPar']))
            ->defaultSort('date_transfert', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('date_transfert')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->visibleFrom('md'),

                // Source → Destination affichés ensemble pour lisibilité
                Tables\Columns\TextColumn::make('emplacementSource.nom')
                    ->label('De')
                    ->icon('heroicon-m-arrow-right')
                    ->sortable(),

                Tables\Columns\TextColumn::make('emplacementDest.nom')
                    ->label('Vers')
                    ->sortable(),

                // Nombre de produits dans le transfert
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

                Tables\Columns\TextColumn::make('recuPar.nom')
                    ->label('Reçu par')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('date_reception')
                    ->label('Date réception')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(collect(StatutTransfert::cases())->mapWithKeys(
                        fn($e) => [$e->value => $e->getLabel()]
                    )),

                Tables\Filters\SelectFilter::make('emplacement_source_id')
                    ->label('Source')
                    ->relationship('emplacementSource', 'nom'),

                Tables\Filters\SelectFilter::make('emplacement_dest_id')
                    ->label('Destination')
                    ->relationship('emplacementDest', 'nom'),
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
            'index' => Pages\ListTransferts::route('/'),
            'create' => Pages\CreateTransfert::route('/create'),
            'edit' => Pages\EditTransfert::route('/{record}/edit'),
        ];
    }
}
