<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ModePaiement;
use App\Enums\StatutFacture;
use App\Filament\Resources\LotResource\Pages;
use App\Models\Lot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class LotResource extends Resource
{
    protected static ?string $model = Lot::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Achats';

    protected static ?string $modelLabel = 'Lot';

    protected static ?string $pluralModelLabel = 'Lots';

    protected static ?int $navigationSort = 1;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Réception')
                ->icon('heroicon-o-archive-box')
                ->schema([
                    Infolists\Components\TextEntry::make('reference')->label('Référence')->weight('bold')->copyable(),
                    Infolists\Components\TextEntry::make('date_reception')->label('Date réception')->date('d/m/Y'),
                    Infolists\Components\TextEntry::make('fournisseur.nom')->label('Fournisseur')->icon('heroicon-o-truck'),
                    Infolists\Components\TextEntry::make('type_produit')->label('Type')->badge()
                        ->formatStateUsing(fn(string $state) => match ($state) { 'poulet' => 'Poulet', 'oeuf' => 'Œuf', default => $state })
                        ->color(fn(string $state) => match ($state) { 'poulet' => 'warning', 'oeuf' => 'info', default => 'gray' }),
                ])->columns(4),

            Infolists\Components\Section::make('Quantités & Coûts')
                ->icon('heroicon-o-calculator')
                ->schema([
                    Infolists\Components\TextEntry::make('quantite_recue')->label('Qté reçue')->numeric(0),
                    Infolists\Components\TextEntry::make('quantite_morts')->label('Morts (PM)')->numeric(0)->color('danger'),
                    Infolists\Components\TextEntry::make('quantite_refuses')->label('Refusés (IR)')->numeric(0)->color('warning'),
                    Infolists\Components\TextEntry::make('quantite_utilisable')->label('Utilisable')->numeric(0)->weight('bold')->color('success'),
                    Infolists\Components\TextEntry::make('prix_unitaire')->label('Prix unitaire')->numeric(0)->suffix(' FCFA'),
                    Infolists\Components\TextEntry::make('cout_transport')->label('Transport')->numeric(0)->suffix(' FCFA'),
                    Infolists\Components\TextEntry::make('cout_total')->label('Coût total')->numeric(0)->suffix(' FCFA')->weight('bold'),
                    Infolists\Components\TextEntry::make('cout_moyen_unitaire')->label('CMP')->numeric(2)->suffix(' FCFA')->color('primary'),
                ])->columns(4),

            Infolists\Components\Section::make('Facturation')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Infolists\Components\TextEntry::make('montant_facture')->label('Montant facture')->numeric(0)->suffix(' FCFA'),
                    Infolists\Components\TextEntry::make('montant_regle')->label('Montant réglé')->numeric(0)->suffix(' FCFA')->color('success'),
                    Infolists\Components\TextEntry::make('statut_facture')->label('Statut')->badge(),
                    Infolists\Components\TextEntry::make('mode_reglement')->label('Mode')->placeholder('—'),
                    Infolists\Components\TextEntry::make('date_reglement')->label('Date règlement')->date('d/m/Y')->placeholder('—'),
                    Infolists\Components\TextEntry::make('observations')->label('Observations')->placeholder('—')->columnSpanFull(),
                ])->columns(4)
                ->collapsible(),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Section 1 : Réception
            Forms\Components\Section::make('Réception du lot')
                ->description('Informations sur la réception des marchandises')
                ->schema([
                    Forms\Components\TextInput::make('reference')
                        ->label('Référence')
                        ->disabled()
                        ->dehydrated(false)
                        ->hiddenOn('create')
                        ->helperText('Générée automatiquement'),
                    Forms\Components\DatePicker::make('date_reception')
                        ->label('Date de réception')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    Forms\Components\Select::make('fournisseur_id')
                        ->label('Fournisseur')
                        ->relationship('fournisseur', 'nom')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('nom')
                                ->label('Nom')
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
                                ->placeholder('0701020304'),
                        ]),
                    Forms\Components\Select::make('type_produit')
                        ->label('Type de produit')
                        ->options([
                            'poulet' => 'Poulet',
                            'oeuf' => 'Œuf',
                        ])
                        ->required()
                        ->live(),
                ])->columns(2),

            // Section 2 : Quantités
            Forms\Components\Section::make('Quantités')
                ->description('Détail des quantités reçues et pertes')
                ->schema([
                    Forms\Components\TextInput::make('quantite_recue')
                        ->label('Quantité reçue')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::calculerTotaux($get, $set)),
                    Forms\Components\TextInput::make('quantite_morts')
                        ->label('Poulets morts (PM)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::calculerTotaux($get, $set))
                        ->visible(fn(Get $get): bool => $get('type_produit') === 'poulet'),
                    Forms\Components\TextInput::make('quantite_refuses')
                        ->label('Indésirables refusés (IR)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::calculerTotaux($get, $set))
                        ->visible(fn(Get $get): bool => $get('type_produit') === 'poulet'),
                    Forms\Components\TextInput::make('oeufs_casses')
                        ->label('Œufs cassés')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::calculerTotaux($get, $set))
                        ->visible(fn(Get $get): bool => $get('type_produit') === 'oeuf'),
                    Forms\Components\Placeholder::make('quantite_utilisable_display')
                        ->label('Quantité utilisable')
                        ->content(function (Get $get): string {
                            $recue = (int) ($get('quantite_recue') ?? 0);
                            $morts = (int) ($get('quantite_morts') ?? 0);
                            $refuses = (int) ($get('quantite_refuses') ?? 0);
                            $casses = (int) ($get('oeufs_casses') ?? 0);
                            $utilisable = $recue - $morts - $refuses - $casses;

                            return number_format(max(0, $utilisable), 0, ',', ' ');
                        }),
                ])->columns(2),

            // Section 3 : Coûts
            Forms\Components\Section::make('Coûts')
                ->description('Prix d\'achat et frais annexes')
                ->schema([
                    Forms\Components\TextInput::make('prix_unitaire')
                        ->label('Prix unitaire (FCFA)')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->suffix('FCFA')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::calculerTotaux($get, $set)),
                    Forms\Components\TextInput::make('cout_transport')
                        ->label('Coût transport (FCFA)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->suffix('FCFA')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::calculerTotaux($get, $set)),
                    Forms\Components\TextInput::make('autres_couts')
                        ->label('Autres coûts (FCFA)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->suffix('FCFA')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::calculerTotaux($get, $set)),
                    Forms\Components\Placeholder::make('cout_total_display')
                        ->label('Coût total')
                        ->content(function (Get $get): string {
                            $coutTotal = static::calculerCoutTotal($get);

                            return number_format($coutTotal, 0, ',', ' ') . ' FCFA';
                        }),
                    Forms\Components\Placeholder::make('cout_moyen_display')
                        ->label('Coût moyen unitaire')
                        ->content(function (Get $get): string {
                            $coutTotal = static::calculerCoutTotal($get);
                            $utilisable = static::calculerQuantiteUtilisable($get);
                            if ($utilisable <= 0) {
                                return '—';
                            }

                            return number_format($coutTotal / $utilisable, 2, ',', ' ') . ' FCFA';
                        }),
                ])->columns(2),

            // Section 4 : Facturation
            Forms\Components\Section::make('Facturation & Règlement')
                ->schema([
                    Forms\Components\TextInput::make('montant_facture')
                        ->label('Montant facture (FCFA)')
                        ->numeric()
                        ->default(0)
                        ->suffix('FCFA')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::calculerStatutFacture($get, $set)),
                    Forms\Components\TextInput::make('montant_regle')
                        ->label('Montant réglé (FCFA)')
                        ->numeric()
                        ->default(0)
                        ->suffix('FCFA')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(Get $get, Set $set) => static::calculerStatutFacture($get, $set)),
                    Forms\Components\Select::make('mode_reglement')
                        ->label('Mode de règlement')
                        ->options(collect(ModePaiement::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()])),
                    Forms\Components\DatePicker::make('date_reglement')
                        ->label('Date de règlement')
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    Forms\Components\Placeholder::make('statut_facture_display')
                        ->label('Statut facture')
                        ->content(function (Get $get): string {
                            $facture = (float) ($get('montant_facture') ?? 0);
                            $regle = (float) ($get('montant_regle') ?? 0);
                            if ($facture <= 0) {
                                return '—';
                            }
                            if ($regle <= 0) {
                                return '🔴 Non réglée';
                            }
                            if ($regle < $facture) {
                                return '🟡 Partielle (' . number_format($regle, 0, ',', ' ') . ' / ' . number_format($facture, 0, ',', ' ') . ' FCFA)';
                            }

                            return '🟢 Réglée';
                        }),
                ])->columns(2),

            // Section 5 : Observations & PJ
            Forms\Components\Section::make('Compléments')
                ->schema([
                    Forms\Components\DatePicker::make('date_production')
                        ->label('Date d\'entrée en production')
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    Forms\Components\FileUpload::make('piece_jointe')
                        ->label('Pièce jointe (facture / BL)')
                        ->directory('lots-pj')
                        ->acceptedFileTypes(['image/*', 'application/pdf'])
                        ->maxSize(5120)
                        ->openable()
                        ->downloadable(),
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
            ->modifyQueryUsing(fn($query) => $query->with(['fournisseur']))
            ->defaultSort('date_reception', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('date_reception')
                    ->label('Date réception')
                    ->date('d/m/Y')
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('fournisseur.nom')
                    ->label('Fournisseur')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type_produit')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'poulet' => 'Poulet',
                        'oeuf' => 'Œuf',
                        default => $state,
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'poulet' => 'warning',
                        'oeuf' => 'info',
                        default => 'gray',
                    })
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('quantite_recue')
                    ->label('Qté reçue')
                    ->numeric(0)
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('quantite_utilisable')
                    ->label('Qté utilisable')
                    ->numeric(0)
                    ->sortable(),
                Tables\Columns\TextColumn::make('cout_total')
                    ->label('Coût total')
                    ->numeric(0)
                    ->suffix(' FCFA')
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('cout_moyen_unitaire')
                    ->label('CMP')
                    ->numeric(2)
                    ->suffix(' FCFA')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('statut_facture')
                    ->label('Facture')
                    ->badge(),
                Tables\Columns\TextColumn::make('montant_regle')
                    ->label('Réglé')
                    ->numeric(0)
                    ->suffix(' FCFA')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('fournisseur_id')
                    ->label('Fournisseur')
                    ->relationship('fournisseur', 'nom'),
                Tables\Filters\SelectFilter::make('type_produit')
                    ->label('Type')
                    ->options([
                        'poulet' => 'Poulet',
                        'oeuf' => 'Œuf',
                    ]),
                Tables\Filters\SelectFilter::make('statut_facture')
                    ->label('Statut facture')
                    ->options(collect(StatutFacture::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Marquer les lots sélectionnés comme facture réglée
                Tables\Actions\BulkAction::make('marquer_regle')
                    ->label('Marquer facture réglée')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Marquer comme réglé')
                    ->modalDescription('Les lots sélectionnés seront marqués avec le statut facture "Réglée". Continuer ?')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $records->each(function (Lot $lot) {
                            $lot->update([
                                'statut_facture' => StatutFacture::REGLEE->value,
                                'montant_regle' => $lot->montant_facture,
                                'date_reglement' => now()->toDateString(),
                            ]);
                        });

                        \Filament\Notifications\Notification::make()
                            ->title($records->count() . ' lot(s) marqué(s) comme réglé(s)')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                // Export CSV des lots sélectionnés
                Tables\Actions\BulkAction::make('exporter')
                    ->label('Exporter Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        return response()->streamDownload(function () use ($records) {
                            $csv = implode(',', [
                                'Référence', 'Date', 'Fournisseur', 'Type',
                                'Qté reçue', 'Utilisable', 'Coût total', 'CMP', 'Statut facture',
                            ]) . "\n";
                            foreach ($records as $lot) {
                                $csv .= implode(',', [
                                    $lot->reference,
                                    $lot->date_reception->format('d/m/Y'),
                                    '"' . ($lot->fournisseur?->nom ?? '—') . '"',
                                    $lot->type_produit,
                                    $lot->quantite_recue,
                                    $lot->quantite_utilisable,
                                    (float) $lot->cout_total,
                                    (float) $lot->cout_moyen_unitaire,
                                    $lot->statut_facture?->getLabel() ?? '—',
                                ]) . "\n";
                            }
                            echo $csv;
                        }, 'lots-' . now()->format('Y-m-d') . '.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
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
            'index'  => Pages\ListLots::route('/'),
            'create' => Pages\CreateLot::route('/create'),
            'view'   => Pages\ViewLot::route('/{record}'),
            'edit'   => Pages\EditLot::route('/{record}/edit'),
        ];
    }

    // --- Méthodes de calcul ---

    protected static function calculerQuantiteUtilisable(Get $get): int
    {
        $recue = (int) ($get('quantite_recue') ?? 0);
        $morts = (int) ($get('quantite_morts') ?? 0);
        $refuses = (int) ($get('quantite_refuses') ?? 0);
        $casses = (int) ($get('oeufs_casses') ?? 0);

        return max(0, $recue - $morts - $refuses - $casses);
    }

    protected static function calculerCoutTotal(Get $get): float
    {
        $recue = (int) ($get('quantite_recue') ?? 0);
        $prixUnitaire = (float) ($get('prix_unitaire') ?? 0);
        $transport = (float) ($get('cout_transport') ?? 0);
        $autres = (float) ($get('autres_couts') ?? 0);

        return ($prixUnitaire * $recue) + $transport + $autres;
    }

    protected static function calculerTotaux(Get $get, Set $set): void
    {
        $utilisable = static::calculerQuantiteUtilisable($get);
        $set('quantite_utilisable', $utilisable);

        $coutTotal = static::calculerCoutTotal($get);
        $set('cout_total', round($coutTotal, 2));

        $cmp = $utilisable > 0 ? $coutTotal / $utilisable : 0;
        $set('cout_moyen_unitaire', round($cmp, 4));
    }

    protected static function calculerStatutFacture(Get $get, Set $set): void
    {
        $facture = (float) ($get('montant_facture') ?? 0);
        $regle = (float) ($get('montant_regle') ?? 0);

        if ($facture <= 0) {
            $set('statut_facture', StatutFacture::NON_REGLEE->value);

            return;
        }

        if ($regle <= 0) {
            $set('statut_facture', StatutFacture::NON_REGLEE->value);
        } elseif ($regle < $facture) {
            $set('statut_facture', StatutFacture::PARTIELLE->value);
        } else {
            $set('statut_facture', StatutFacture::REGLEE->value);
        }
    }
}
