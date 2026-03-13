<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\CategorieProduit;
use App\Filament\Resources\ProduitResource\Pages;
use App\Models\Produit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProduitResource extends Resource
{
    protected static ?string $model = Produit::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Référentiels';

    protected static ?string $modelLabel = 'Produit';

    protected static ?string $pluralModelLabel = 'Produits';

    protected static ?int $navigationSort = 3;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Informations produit')
                ->icon('heroicon-o-cube')
                ->schema([
                    Infolists\Components\TextEntry::make('code')
                        ->label('Code')
                        ->badge()
                        ->color('gray'),

                    Infolists\Components\TextEntry::make('nom')
                        ->label('Nom')
                        ->weight('bold')
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                    Infolists\Components\TextEntry::make('categorie')
                        ->label('Catégorie')
                        ->badge(),

                    Infolists\Components\TextEntry::make('unite_stock')
                        ->label('Unité de stock')
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'kg' => 'Kilogramme (kg)',
                            'unite' => 'Unité',
                            'paquet' => 'Paquet',
                            default => $state,
                        }),

                    Infolists\Components\TextEntry::make('unite_vente')
                        ->label('Unité de vente')
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'kg' => 'Kilogramme (kg)',
                            'unite' => 'Unité',
                            'paquet' => 'Paquet',
                            default => $state,
                        }),

                    Infolists\Components\TextEntry::make('prix_vente_defaut')
                        ->label('Prix de vente par défaut')
                        ->numeric(0)
                        ->suffix(' FCFA')
                        ->weight('bold')
                        ->color('primary'),

                    Infolists\Components\TextEntry::make('seuil_alerte_stock')
                        ->label('Seuil alerte stock')
                        ->numeric(2)
                        ->placeholder('Non défini'),

                    Infolists\Components\IconEntry::make('actif')
                        ->label('Actif')
                        ->boolean(),
                ])->columns(2),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informations produit')->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(20),
                Forms\Components\TextInput::make('nom')
                    ->label('Nom')
                    ->required()
                    ->maxLength(100),
                Forms\Components\Select::make('categorie')
                    ->label('Catégorie')
                    ->options(collect(CategorieProduit::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()]))
                    ->required(),
                Forms\Components\Select::make('unite_stock')
                    ->label('Unité de stock')
                    ->options([
                        'kg' => 'Kilogramme (kg)',
                        'unite' => 'Unité',
                        'paquet' => 'Paquet',
                    ])
                    ->required(),
                Forms\Components\Select::make('unite_vente')
                    ->label('Unité de vente')
                    ->options([
                        'kg' => 'Kilogramme (kg)',
                        'unite' => 'Unité',
                        'paquet' => 'Paquet',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('prix_vente_defaut')
                    ->label('Prix de vente par défaut (FCFA)')
                    ->numeric()
                    ->suffix('FCFA')
                    ->default(0),
                Forms\Components\TextInput::make('seuil_alerte_stock')
                    ->label('Seuil alerte stock')
                    ->numeric()
                    ->helperText('Stock minimum avant alerte'),
                Forms\Components\Toggle::make('actif')
                    ->label('Actif')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nom')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('categorie')
                    ->label('Catégorie')
                    ->badge(),
                Tables\Columns\TextColumn::make('unite_vente')
                    ->label('Unité'),
                Tables\Columns\TextColumn::make('prix_vente_defaut')
                    ->label('Prix vente (FCFA)')
                    ->numeric(0)
                    ->suffix(' FCFA')
                    ->sortable(),
                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categorie')
                    ->label('Catégorie')
                    ->options(collect(CategorieProduit::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProduits::route('/'),
            'create' => Pages\CreateProduit::route('/create'),
            'edit' => Pages\EditProduit::route('/{record}/edit'),
        ];
    }
}
