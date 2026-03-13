<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\TypeEmplacement;
use App\Filament\Resources\EmplacementResource\Pages;
use App\Models\Emplacement;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmplacementResource extends Resource
{
    protected static ?string $model = Emplacement::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Référentiels';

    protected static ?string $modelLabel = 'Emplacement';

    protected static ?string $pluralModelLabel = 'Emplacements';

    protected static ?int $navigationSort = 1;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Informations générales')
                ->icon('heroicon-o-building-storefront')
                ->schema([
                    Infolists\Components\TextEntry::make('nom')
                        ->label('Nom')
                        ->weight('bold')
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                    Infolists\Components\TextEntry::make('type')
                        ->label('Type')
                        ->badge(),

                    Infolists\Components\TextEntry::make('adresse')
                        ->label('Adresse')
                        ->placeholder('Non renseignée')
                        ->columnSpanFull(),

                    Infolists\Components\TextEntry::make('telephone')
                        ->label('Téléphone')
                        ->icon('heroicon-o-phone')
                        ->placeholder('Non renseigné'),

                    Infolists\Components\TextEntry::make('responsable.name')
                        ->label('Responsable')
                        ->icon('heroicon-o-user')
                        ->placeholder('Non assigné'),

                    Infolists\Components\IconEntry::make('actif')
                        ->label('Actif')
                        ->boolean(),
                ])->columns(3),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informations générales')->schema([
                Forms\Components\TextInput::make('nom')
                    ->label('Nom')
                    ->required()
                    ->maxLength(100),
                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options(collect(TypeEmplacement::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()]))
                    ->required(),
                Forms\Components\Textarea::make('adresse')
                    ->label('Adresse')
                    ->rows(2),
                Forms\Components\TextInput::make('telephone')
                    ->label('Téléphone')
                    ->tel()
                    ->mask('9999999999')
                    ->placeholder('0701020304')
                    ->helperText('10 chiffres uniquement'),
                Forms\Components\Select::make('responsable_id')
                    ->label('Responsable')
                    ->relationship('responsable', 'name')
                    ->searchable()
                    ->preload(),
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
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type'),
                Tables\Columns\TextColumn::make('telephone')
                    ->label('Téléphone'),
                Tables\Columns\TextColumn::make('responsable.name')
                    ->label('Responsable'),
                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([])
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
            'index' => Pages\ListEmplacements::route('/'),
            'create' => Pages\CreateEmplacement::route('/create'),
            'edit' => Pages\EditEmplacement::route('/{record}/edit'),
        ];
    }
}
