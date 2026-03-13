<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\FournisseurResource\Pages;
use App\Models\Fournisseur;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FournisseurResource extends Resource
{
    protected static ?string $model = Fournisseur::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Référentiels';

    protected static ?string $modelLabel = 'Fournisseur';

    protected static ?string $pluralModelLabel = 'Fournisseurs';

    protected static ?int $navigationSort = 2;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Informations fournisseur')
                ->icon('heroicon-o-truck')
                ->schema([
                    Infolists\Components\TextEntry::make('nom')
                        ->label('Nom / Raison sociale')
                        ->weight('bold')
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                    Infolists\Components\TextEntry::make('type')
                        ->label('Type')
                        ->badge(),

                    Infolists\Components\TextEntry::make('telephone')
                        ->label('Téléphone')
                        ->icon('heroicon-o-phone')
                        ->placeholder('Non renseigné'),

                    Infolists\Components\TextEntry::make('email')
                        ->label('Email')
                        ->icon('heroicon-o-envelope')
                        ->copyable()
                        ->placeholder('Non renseigné'),

                    Infolists\Components\TextEntry::make('adresse')
                        ->label('Adresse')
                        ->placeholder('Non renseignée')
                        ->columnSpanFull(),

                    Infolists\Components\IconEntry::make('actif')
                        ->label('Actif')
                        ->boolean(),
                ])->columns(2),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informations fournisseur')->schema([
                Forms\Components\TextInput::make('nom')
                    ->label('Nom / Raison sociale')
                    ->required()
                    ->maxLength(150),
                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options([
                        'volaille' => 'Volaille',
                        'oeuf' => 'Œuf',
                        'matiere_premiere' => 'Matière première',
                        'autre' => 'Autre',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('telephone')
                    ->label('Téléphone')
                    ->tel()
                    ->mask('9999999999')
                    ->placeholder('0701020304')
                    ->helperText('10 chiffres uniquement'),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(150),
                Forms\Components\Textarea::make('adresse')
                    ->label('Adresse')
                    ->rows(2)
                    ->columnSpanFull(),
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
                    ->label('Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('telephone')
                    ->label('Téléphone'),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFournisseurs::route('/'),
            'create' => Pages\CreateFournisseur::route('/create'),
            'edit' => Pages\EditFournisseur::route('/{record}/edit'),
        ];
    }
}
