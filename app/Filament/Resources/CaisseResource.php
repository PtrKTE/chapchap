<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\StatutCaisse;
use App\Filament\Resources\CaisseResource\Pages;
use App\Models\Caisse;
use App\Models\Emplacement;
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
 * Resource Filament pour le journal de caisse journalier.
 */
class CaisseResource extends Resource
{
    protected static ?string $model = Caisse::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Trésorerie';

    protected static ?string $modelLabel = 'Caisse';

    protected static ?string $pluralModelLabel = 'Caisses';

    protected static ?int $navigationSort = 1;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Identification')
                ->icon('heroicon-o-calculator')
                ->schema([
                    Infolists\Components\TextEntry::make('date_caisse')->label('Date')->date('d/m/Y')->weight('bold'),
                    Infolists\Components\TextEntry::make('emplacement.nom')->label('Emplacement')->icon('heroicon-o-map-pin'),
                    Infolists\Components\TextEntry::make('responsable.name')->label('Responsable')->icon('heroicon-o-user'),
                    Infolists\Components\TextEntry::make('statut')->label('Statut')->badge(),
                ])->columns(4),

            Infolists\Components\Section::make('Flux financiers')
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Infolists\Components\TextEntry::make('total_encaisse')->label('Encaissé (ventes)')->numeric(0)->suffix(' FCFA')->color('success'),
                    Infolists\Components\TextEntry::make('total_credits_encaisses')->label('Crédits encaissés')->numeric(0)->suffix(' FCFA')->color('success'),
                    Infolists\Components\TextEntry::make('total_decaisse')->label('Décaissé (charges)')->numeric(0)->suffix(' FCFA')->color('danger'),
                    Infolists\Components\TextEntry::make('solde_caisse')->label('Solde')->numeric(0)->suffix(' FCFA')->weight('bold')
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                ])->columns(4),

            Infolists\Components\Section::make('Versements')
                ->icon('heroicon-o-arrow-up-tray')
                ->schema([
                    Infolists\Components\TextEntry::make('montant_verse')->label('Espèces versées')->numeric(0)->suffix(' FCFA'),
                    Infolists\Components\TextEntry::make('depot_wave_mtn')->label('Wave / MTN')->numeric(0)->suffix(' FCFA'),
                    Infolists\Components\TextEntry::make('depot_cheque')->label('Chèque')->numeric(0)->suffix(' FCFA'),
                    Infolists\Components\TextEntry::make('montant_especes')->label('Espèces en caisse')->numeric(0)->suffix(' FCFA'),
                    Infolists\Components\TextEntry::make('ecart')->label('Écart')->numeric(0)->suffix(' FCFA')->weight('bold')
                        ->color(fn($state) => abs((float) $state) < 1 ? 'success' : 'danger'),
                    Infolists\Components\TextEntry::make('observations')->label('Observations')->placeholder('—')->columnSpanFull(),
                ])->columns(4)
                ->collapsible(),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Section 1 : Identification
            Forms\Components\Section::make('Identification de la caisse')
                ->schema([
                    Forms\Components\DatePicker::make('date_caisse')
                        ->label('Date')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    Forms\Components\Select::make('emplacement_id')
                        ->label('Emplacement')
                        ->options(Emplacement::where('actif', true)->pluck('nom', 'id'))
                        ->required()
                        ->default(1),

                    Forms\Components\Select::make('responsable_id')
                        ->label('Responsable de caisse')
                        ->relationship('responsable', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->default(fn() => auth()->id()),

                    Forms\Components\Select::make('statut')
                        ->label('Statut')
                        ->options(collect(StatutCaisse::cases())->mapWithKeys(
                            fn($e) => [$e->value => $e->getLabel()]
                        ))
                        ->default('ouverte')
                        ->disabled(),
                ])->columns(2),

            // Section 2 : Encaissements (ce qui rentre)
            Forms\Components\Section::make('Encaissements')
                ->description('Montants encaissés dans la journée')
                ->schema([
                    Forms\Components\TextInput::make('total_encaisse')
                        ->label('Total encaissé (ventes du jour)')
                        ->numeric()
                        ->default(0)
                        ->suffix('FCFA')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::recalculer($get, $set)),

                    Forms\Components\TextInput::make('total_credits_encaisses')
                        ->label('Crédits encaissés (paiements sur ventes antérieures)')
                        ->numeric()
                        ->default(0)
                        ->suffix('FCFA')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::recalculer($get, $set)),
                ])->columns(2),

            // Section 3 : Décaissements (ce qui sort)
            Forms\Components\Section::make('Décaissements')
                ->description('Charges et dépenses de la journée')
                ->schema([
                    Forms\Components\TextInput::make('total_decaisse')
                        ->label('Total décaissé (charges du jour)')
                        ->numeric()
                        ->default(0)
                        ->suffix('FCFA')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::recalculer($get, $set)),
                ]),

            // Section 4 : Solde et versements
            Forms\Components\Section::make('Solde & Versements')
                ->description('Répartition des versements de fin de journée')
                ->schema([
                    // Solde calculé (lecture seule)
                    Forms\Components\Placeholder::make('solde_display')
                        ->label('Solde de caisse')
                        ->content(function (Get $get): string {
                            $encaisse = (float) ($get('total_encaisse') ?? 0);
                            $credits = (float) ($get('total_credits_encaisses') ?? 0);
                            $decaisse = (float) ($get('total_decaisse') ?? 0);
                            $solde = $encaisse + $credits - $decaisse;

                            return number_format($solde, 0, ',', ' ') . ' FCFA';
                        }),

                    // Montant versé en espèces
                    Forms\Components\TextInput::make('montant_verse')
                        ->label('Versement espèces')
                        ->numeric()
                        ->default(0)
                        ->suffix('FCFA')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::recalculer($get, $set)),

                    // Dépôts Wave / MTN Money
                    Forms\Components\TextInput::make('depot_wave_mtn')
                        ->label('Dépôts Wave / MTN Money')
                        ->numeric()
                        ->default(0)
                        ->suffix('FCFA')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::recalculer($get, $set)),

                    // Dépôts chèque
                    Forms\Components\TextInput::make('depot_cheque')
                        ->label('Dépôts chèque')
                        ->numeric()
                        ->default(0)
                        ->suffix('FCFA')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::recalculer($get, $set)),

                    // Montant espèces restant
                    Forms\Components\TextInput::make('montant_especes')
                        ->label('Espèces en caisse')
                        ->numeric()
                        ->default(0)
                        ->suffix('FCFA')
                        ->helperText('Espèces restantes physiquement en caisse'),

                    // Écart calculé (lecture seule)
                    Forms\Components\Placeholder::make('ecart_display')
                        ->label('Écart')
                        ->content(function (Get $get): string {
                            $encaisse = (float) ($get('total_encaisse') ?? 0);
                            $credits = (float) ($get('total_credits_encaisses') ?? 0);
                            $decaisse = (float) ($get('total_decaisse') ?? 0);
                            $solde = $encaisse + $credits - $decaisse;

                            $verse = (float) ($get('montant_verse') ?? 0);
                            $wave = (float) ($get('depot_wave_mtn') ?? 0);
                            $cheque = (float) ($get('depot_cheque') ?? 0);
                            $ecart = $solde - ($verse + $wave + $cheque);

                            $color = abs($ecart) < 1 ? 'success' : 'danger';
                            $label = abs($ecart) < 1 ? '0 FCFA (OK)' : number_format($ecart, 0, ',', ' ') . ' FCFA';

                            return $label;
                        }),
                ])->columns(2),

            // Section 5 : Observations
            Forms\Components\Section::make('Observations')
                ->schema([
                    Forms\Components\Textarea::make('observations')
                        ->label('Observations')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with(['emplacement', 'responsable']))
            ->defaultSort('date_caisse', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date_caisse')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('emplacement.nom')
                    ->label('Emplacement')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_encaisse')
                    ->label('Encaissé')
                    ->numeric(0)
                    ->suffix(' FCFA')
                    ->color('success')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('total_decaisse')
                    ->label('Décaissé')
                    ->numeric(0)
                    ->suffix(' FCFA')
                    ->color('danger')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('solde_caisse')
                    ->label('Solde')
                    ->numeric(0)
                    ->suffix(' FCFA')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('ecart')
                    ->label('Écart')
                    ->numeric(0)
                    ->suffix(' FCFA')
                    ->color(fn($state) => abs((float) $state) < 1 ? 'success' : 'danger')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),

                Tables\Columns\TextColumn::make('responsable.name')
                    ->label('Responsable')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('emplacement_id')
                    ->label('Emplacement')
                    ->relationship('emplacement', 'nom'),

                Tables\Filters\SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(collect(StatutCaisse::cases())->mapWithKeys(
                        fn($e) => [$e->value => $e->getLabel()]
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Valider les caisses sélectionnées
                Tables\Actions\BulkAction::make('valider')
                    ->label('Valider les caisses')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Valider les caisses')
                    ->modalDescription('Les caisses clôturées sélectionnées seront marquées comme validées. Continuer ?')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $count = 0;
                        $records->each(function (Caisse $caisse) use (&$count) {
                            if ($caisse->statut === StatutCaisse::CLOTUREE) {
                                $caisse->update([
                                    'statut' => StatutCaisse::VALIDEE->value,
                                    'valide_par' => auth()->id(),
                                ]);
                                $count++;
                            }
                        });

                        \Filament\Notifications\Notification::make()
                            ->title($count . ' caisse(s) validée(s)')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCaisses::route('/'),
            'create' => Pages\CreateCaisse::route('/create'),
            'edit' => Pages\EditCaisse::route('/{record}/edit'),
        ];
    }

    /**
     * Recalcule le solde et l'écart à chaque modification des champs.
     */
    protected static function recalculer(Get $get, Set $set): void
    {
        $encaisse = (float) ($get('total_encaisse') ?? 0);
        $credits = (float) ($get('total_credits_encaisses') ?? 0);
        $decaisse = (float) ($get('total_decaisse') ?? 0);
        $solde = $encaisse + $credits - $decaisse;

        $set('solde_caisse', round($solde, 2));

        $verse = (float) ($get('montant_verse') ?? 0);
        $wave = (float) ($get('depot_wave_mtn') ?? 0);
        $cheque = (float) ($get('depot_cheque') ?? 0);
        $ecart = $solde - ($verse + $wave + $cheque);

        $set('ecart', round($ecart, 2));
    }
}
