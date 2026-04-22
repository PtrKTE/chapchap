<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\TypeCharge;
use App\Filament\Resources\CategorieChargeResource\Pages;
use App\Models\CategorieCharge;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategorieChargeResource extends Resource
{
    protected static ?string $model = CategorieCharge::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Référentiels';

    protected static ?string $modelLabel = 'Catégorie de charge';

    protected static ?string $pluralModelLabel = 'Catégories de charges';

    protected static ?int $navigationSort = 4;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Catégorie de charge')
                ->icon('heroicon-o-tag')
                ->schema([
                    Infolists\Components\TextEntry::make('nom')
                        ->label('Nom')
                        ->weight('bold')
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                    Infolists\Components\TextEntry::make('type')
                        ->label('Type')
                        ->badge(),

                    Infolists\Components\TextEntry::make('montant_reference')
                        ->label('Montant de référence mensuel')
                        ->numeric(0)
                        ->suffix(' FCFA')
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
            Forms\Components\Section::make('Catégorie de charge')->schema([
                Forms\Components\TextInput::make('nom')
                    ->label('Nom')
                    ->required()
                    ->maxLength(100),
                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options(collect(TypeCharge::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()]))
                    ->required(),
                Forms\Components\TextInput::make('montant_reference')
                    ->label('Montant de référence mensuel (FCFA)')
                    ->numeric()
                    ->suffix('FCFA')
                    ->helperText('Budget mensuel de référence'),
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
                Tables\Columns\TextColumn::make('nom')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('montant_reference')
                    ->label('Réf. mensuelle (FCFA)')
                    ->numeric(0)
                    ->suffix(' FCFA'),
                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options(collect(TypeCharge::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()])),
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
            'index' => Pages\ListCategorieCharges::route('/'),
            'create' => Pages\CreateCategorieCharge::route('/create'),
            'edit' => Pages\EditCategorieCharge::route('/{record}/edit'),
        ];
    }
}
