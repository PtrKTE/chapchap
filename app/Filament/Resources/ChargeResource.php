<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ModePaiement;
use App\Enums\TypeCharge;
use App\Filament\Resources\ChargeResource\Pages;
use App\Models\CategorieCharge;
use App\Models\Charge;
use App\Models\Emplacement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Resource Filament pour la saisie des charges / dépenses.
 */
class ChargeResource extends Resource
{
    protected static ?string $model = Charge::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-down';

    protected static ?string $navigationGroup = 'Trésorerie';

    protected static ?string $modelLabel = 'Charge';

    protected static ?string $pluralModelLabel = 'Charges / Dépenses';

    protected static ?int $navigationSort = 2;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Détail de la charge')
                ->icon('heroicon-o-arrow-trending-down')
                ->schema([
                    Infolists\Components\TextEntry::make('date_charge')->label('Date')->date('d/m/Y')->weight('bold'),
                    Infolists\Components\TextEntry::make('categorie.nom')->label('Catégorie')->badge()->color('warning'),
                    Infolists\Components\TextEntry::make('libelle')->label('Libellé'),
                    Infolists\Components\TextEntry::make('montant')->label('Montant')->numeric(0)->suffix(' FCFA')->weight('bold')->color('danger')
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                    Infolists\Components\TextEntry::make('mode_paiement')->label('Mode')->badge(),
                    Infolists\Components\TextEntry::make('emplacement.nom')->label('Emplacement')->icon('heroicon-o-map-pin'),
                ])->columns(3),

            Infolists\Components\Section::make('Compléments')
                ->schema([
                    Infolists\Components\TextEntry::make('fournisseur.nom')->label('Fournisseur')->placeholder('—'),
                    Infolists\Components\TextEntry::make('personne')->label('Personne')->placeholder('—'),
                    Infolists\Components\TextEntry::make('createdBy.name')->label('Saisi par'),
                    Infolists\Components\TextEntry::make('observations')->label('Observations')->placeholder('—')->columnSpanFull(),
                ])->columns(3)
                ->collapsible(),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Détail de la charge')
                ->description('Informations sur la dépense à enregistrer')
                ->schema([
                    Forms\Components\DatePicker::make('date_charge')
                        ->label('Date')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    // Catégorie de charge (avec création rapide inline)
                    Forms\Components\Select::make('categorie_id')
                        ->label('Catégorie')
                        ->relationship('categorie', 'nom')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('nom')
                                ->label('Nom')
                                ->required()
                                ->maxLength(100),
                            Forms\Components\Select::make('type')
                                ->label('Type')
                                ->options(collect(TypeCharge::cases())->mapWithKeys(
                                    fn($e) => [$e->value => $e->getLabel()]
                                ))
                                ->required(),
                        ]),

                    // Description libre de la dépense
                    Forms\Components\TextInput::make('libelle')
                        ->label('Libellé / Description')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Ex: Carburant livraison Belleville, Électricité janvier'),

                    // Montant
                    Forms\Components\TextInput::make('montant')
                        ->label('Montant (FCFA)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->suffix('FCFA'),

                    Forms\Components\Select::make('mode_paiement')
                        ->label('Mode de paiement')
                        ->options(collect(ModePaiement::cases())->mapWithKeys(
                            fn($e) => [$e->value => $e->getLabel()]
                        ))
                        ->required()
                        ->default('especes'),

                    Forms\Components\Select::make('emplacement_id')
                        ->label('Emplacement')
                        ->options(Emplacement::where('actif', true)->pluck('nom', 'id'))
                        ->required()
                        ->default(1),
                ])->columns(2),

            Forms\Components\Section::make('Compléments')
                ->schema([
                    // Fournisseur (optionnel, si la charge est liée à un fournisseur)
                    Forms\Components\Select::make('fournisseur_id')
                        ->label('Fournisseur')
                        ->relationship('fournisseur', 'nom')
                        ->searchable()
                        ->preload()
                        ->helperText('Optionnel — si la charge est liée à un fournisseur'),

                    // Personne qui a fait la dépense
                    Forms\Components\TextInput::make('personne')
                        ->label('Personne')
                        ->maxLength(100)
                        ->helperText('Qui a effectué la dépense'),

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
            ->modifyQueryUsing(fn($query) => $query->with(['categorie', 'emplacement', 'createdBy']))
            ->defaultSort('date_charge', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date_charge')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('categorie.nom')
                    ->label('Catégorie')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('montant')
                    ->label('Montant')
                    ->numeric(0)
                    ->suffix(' FCFA')
                    ->sortable()
                    ->weight('bold')
                    ->color('danger')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()
                        ->label('Total')
                        ->numeric(0)
                        ->suffix(' FCFA')
                    ),

                Tables\Columns\TextColumn::make('mode_paiement')
                    ->label('Mode')
                    ->badge()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('emplacement.nom')
                    ->label('Emplacement')
                    ->toggleable()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('personne')
                    ->label('Personne')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Saisi par')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categorie_id')
                    ->label('Catégorie')
                    ->relationship('categorie', 'nom'),

                Tables\Filters\SelectFilter::make('mode_paiement')
                    ->label('Mode de paiement')
                    ->options(collect(ModePaiement::cases())->mapWithKeys(
                        fn($e) => [$e->value => $e->getLabel()]
                    )),

                Tables\Filters\SelectFilter::make('emplacement_id')
                    ->label('Emplacement')
                    ->relationship('emplacement', 'nom'),

                // Filtre par période
                Tables\Filters\Filter::make('date_charge')
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
                            ->when($data['du'], fn($q) => $q->whereDate('date_charge', '>=', $data['du']))
                            ->when($data['au'], fn($q) => $q->whereDate('date_charge', '<=', $data['au']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Export CSV des charges sélectionnées
                Tables\Actions\BulkAction::make('exporter')
                    ->label('Exporter Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        return response()->streamDownload(function () use ($records) {
                            $csv = implode(',', [
                                'Date', 'Catégorie', 'Libellé', 'Montant (FCFA)', 'Mode', 'Emplacement',
                            ]) . "\n";
                            foreach ($records as $charge) {
                                $csv .= implode(',', [
                                    $charge->date_charge->format('d/m/Y'),
                                    '"' . ($charge->categorie?->nom ?? '—') . '"',
                                    '"' . $charge->libelle . '"',
                                    (float) $charge->montant,
                                    $charge->mode_paiement?->getLabel() ?? '—',
                                    '"' . ($charge->emplacement?->nom ?? '—') . '"',
                                ]) . "\n";
                            }
                            echo $csv;
                        }, 'charges-' . now()->format('Y-m-d') . '.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    })
                    ->deselectRecordsAfterCompletion(),

                // Supprimer en masse
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCharges::route('/'),
            'create' => Pages\CreateCharge::route('/create'),
            'edit' => Pages\EditCharge::route('/{record}/edit'),
        ];
    }
}
