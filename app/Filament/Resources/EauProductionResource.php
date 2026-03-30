<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\EauProductionResource\Pages;
use App\Models\EauProduction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Resource Filament pour la production d'eau en sachet.
 */
class EauProductionResource extends Resource
{
    protected static ?string $model = EauProduction::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'Complémentaires';

    protected static ?string $modelLabel = 'Production eau';

    protected static ?string $pluralModelLabel = 'Productions eau';

    protected static ?int $navigationSort = 1;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Production d\'eau')
                ->icon('heroicon-o-beaker')
                ->schema([
                    Infolists\Components\TextEntry::make('date_production')->label('Date')->date('d/m/Y')->weight('bold'),
                    Infolists\Components\TextEntry::make('nb_paquets_produits')->label('Paquets produits')->numeric(0)->suffix(' paquets')->weight('bold')->color('primary'),
                    Infolists\Components\TextEntry::make('consommation_sachets')->label('Sachets utilisés')->numeric(0)->placeholder('—'),
                    Infolists\Components\TextEntry::make('consommation_energie')->label('Énergie')->numeric(2)->suffix(' kWh')->placeholder('—'),
                    Infolists\Components\TextEntry::make('createdBy.name')->label('Saisi par'),
                    Infolists\Components\TextEntry::make('observations')->label('Observations')->placeholder('—')->columnSpanFull(),
                ])->columns(3),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Production d\'eau en sachet')
                ->description('Enregistrer la production journalière d\'eau')
                ->schema([
                    Forms\Components\DatePicker::make('date_production')
                        ->label('Date de production')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    Forms\Components\TextInput::make('nb_paquets_produits')
                        ->label('Nombre de paquets produits')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->suffix('paquets'),

                    Forms\Components\TextInput::make('consommation_sachets')
                        ->label('Consommation de sachets')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('unités')
                        ->helperText('Nombre de sachets utilisés'),

                    Forms\Components\TextInput::make('consommation_energie')
                        ->label('Consommation énergie (kWh)')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0)
                        ->suffix('kWh'),

                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')
                        ->rows(2)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_production', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date_production')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('nb_paquets_produits')
                    ->label('Paquets produits')
                    ->numeric(0)
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()
                        ->label('Total')
                        ->numeric(0)
                    ),

                Tables\Columns\TextColumn::make('consommation_sachets')
                    ->label('Sachets utilisés')
                    ->numeric(0)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('consommation_energie')
                    ->label('Énergie (kWh)')
                    ->numeric(2)
                    ->suffix(' kWh')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('observations')
                    ->label('Observations')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Saisi par')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('date_production')
                    ->form([
                        Forms\Components\DatePicker::make('du')
                            ->label('Du')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('au')
                            ->label('Au')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['du'], fn($q) => $q->whereDate('date_production', '>=', $data['du']))
                            ->when($data['au'], fn($q) => $q->whereDate('date_production', '<=', $data['au']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Voir'),
                Tables\Actions\EditAction::make()->label('Modifier'),
                Tables\Actions\DeleteAction::make()->label('Supprimer'),
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
            'index'  => Pages\ListEauProductions::route('/'),
            'create' => Pages\CreateEauProduction::route('/create'),
            'view'   => Pages\ViewEauProduction::route('/{record}'),
            'edit'   => Pages\EditEauProduction::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            EauProductionResource\Widgets\EauStatsWidget::class,
        ];
    }
}
