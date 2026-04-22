<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\Canal;
use App\Enums\ModePaiement;
use App\Enums\StatutPaiement;
use App\Filament\Resources\VenteResource\Pages;
use App\Models\Emplacement;
use App\Models\Produit;
use App\Models\Vente;
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
 * Resource Filament pour la gestion des ventes.
 *
 * C'est le formulaire le plus utilisé au quotidien par les commerciaux.
 * Chaque vente contient des lignes de produits avec :
 * - Calcul automatique des montants par ligne (qté × prix)
 * - Calcul du total, remise, montant net
 * - Gestion du crédit : paiement partiel → statut "partiel" ou "crédit"
 *
 * La validation d'une vente déclenche la sortie de stock automatique
 * (via VenteObserver → StockService).
 */
class VenteResource extends Resource
{
    protected static ?string $model = Vente::class;

    // Icône "panier" pour les ventes
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Commercial';

    protected static ?string $modelLabel = 'Vente';

    protected static ?string $pluralModelLabel = 'Ventes';

    protected static ?int $navigationSort = 2;

    /**
     * Infolist : vue optimisée quand on clique sur "Voir" une vente.
     * Affiche un récapitulatif clair sans les champs de formulaire grisés.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            // En-tête : infos générales de la vente
            Infolists\Components\Section::make('Informations de la vente')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Infolists\Components\TextEntry::make('numero_recu')
                        ->label('N° Reçu')
                        ->weight('bold')
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                        ->copyable(),

                    Infolists\Components\TextEntry::make('date_vente')
                        ->label('Date de vente')
                        ->dateTime('d/m/Y H:i'),

                    Infolists\Components\TextEntry::make('client.nom')
                        ->label('Client')
                        ->icon('heroicon-o-user'),

                    Infolists\Components\TextEntry::make('canal')
                        ->label('Canal de vente')
                        ->badge(),

                    Infolists\Components\TextEntry::make('emplacement.nom')
                        ->label('Emplacement')
                        ->icon('heroicon-o-map-pin'),

                    Infolists\Components\TextEntry::make('commercial.name')
                        ->label('Commercial')
                        ->icon('heroicon-o-briefcase')
                ])->columns(3),

            // Lignes de produits vendus
            Infolists\Components\Section::make('Produits vendus')
                ->icon('heroicon-o-shopping-bag')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('lignes')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('produit.nom')
                                ->label('Produit')
                                ->weight('bold'),

                            Infolists\Components\TextEntry::make('quantite')
                                ->label('Quantité')
                                ->numeric(3),

                            Infolists\Components\TextEntry::make('prix_unitaire')
                                ->label('Prix unit.')
                                ->numeric(0)
                                ->suffix(' FCFA'),

                            Infolists\Components\TextEntry::make('montant_ligne')
                                ->label('Montant')
                                ->numeric(0)
                                ->suffix(' FCFA')
                                ->weight('bold')
                                ->color('primary'),
                        ])
                        ->columns(4),
                ]),

            // Récap financier
            Infolists\Components\Section::make('Récapitulatif financier')
                ->icon('heroicon-o-calculator')
                ->schema([
                    Infolists\Components\TextEntry::make('montant_total')
                        ->label('Montant total')
                        ->numeric(0)
                        ->suffix(' FCFA'),

                    Infolists\Components\TextEntry::make('remise')
                        ->label('Remise')
                        ->numeric(0)
                        ->suffix(' FCFA')
                        ->color(fn($state) => (float) $state > 0 ? 'warning' : 'gray'),

                    Infolists\Components\TextEntry::make('montant_net')
                        ->label('Montant net')
                        ->numeric(0)
                        ->suffix(' FCFA')
                        ->weight('bold')
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                    Infolists\Components\TextEntry::make('montant_recu')
                        ->label('Montant reçu')
                        ->numeric(0)
                        ->suffix(' FCFA')
                        ->color('success'),

                    Infolists\Components\TextEntry::make('montant_restant')
                        ->label('Reste à payer')
                        ->numeric(0)
                        ->suffix(' FCFA')
                        ->color(fn($state) => (float) $state > 0 ? 'danger' : 'success')
                        ->weight('bold'),

                    Infolists\Components\TextEntry::make('statut_paiement')
                        ->label('Statut paiement')
                        ->badge(),

                    Infolists\Components\TextEntry::make('mode_paiement')
                        ->label('Mode de paiement')
                        ->badge(),

                    Infolists\Components\TextEntry::make('date_reglement_complet')
                        ->label('Date règlement complet')
                        ->date('d/m/Y')
                ])->columns(4),

            // Observations et infos complémentaires
            Infolists\Components\Section::make('Informations complémentaires')
                ->icon('heroicon-o-information-circle')
                ->schema([
                    Infolists\Components\TextEntry::make('observations')
                        ->label('Observations')
                        ->placeholder('Aucune observation')
                        ->columnSpanFull(),

                    Infolists\Components\TextEntry::make('createdBy.name')
                        ->label('Créée par'),

                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Date de création')
                        ->dateTime('d/m/Y H:i'),

                    Infolists\Components\IconEntry::make('annulee')
                        ->label('Annulée')
                        ->boolean()
                        ->trueColor('danger')
                        ->falseColor('success'),

                    Infolists\Components\TextEntry::make('motif_annulation')
                        ->label('Motif d\'annulation')
                        ->visible(fn($record) => $record->annulee),
                ])->columns(3)
                ->collapsible(),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Section 1 : Informations de la vente
            Forms\Components\Section::make('Informations de la vente')
                ->description('Client, canal de vente et emplacement')
                ->schema([
                    Forms\Components\TextInput::make('numero_recu')
                        ->label('N° Reçu')
                        ->disabled()
                        ->dehydrated(false)
                        ->hiddenOn('create')
                        ->helperText('Généré automatiquement'),

                    Forms\Components\DateTimePicker::make('date_vente')
                        ->label('Date de vente')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y H:i')
                        ->readOnly(fn () => ! (
                            auth()->user()?->hasRole(['gerant', 'super_admin']) ||
                            auth()->user()?->profil === \App\Enums\Profil::GERANT
                        )),

                    // Sélection du client avec recherche et création rapide
                    Forms\Components\Select::make('client_id')
                        ->label('Client')
                        ->relationship('client', 'nom')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('nom')
                                ->label('Nom')
                                ->required()
                                ->maxLength(150),
                            Forms\Components\TextInput::make('telephone')
                                ->label('Téléphone')
                                ->tel()
                                ->mask('9999999999')
                                ->placeholder('0701020304'),
                            Forms\Components\Select::make('type_client')
                                ->label('Type')
                                ->options(collect(\App\Enums\TypeClient::cases())->mapWithKeys(
                                    fn($e) => [$e->value => $e->getLabel()]
                                ))
                                ->required(),
                        ])
                        ->createOptionUsing(function (array $data): int {
                            // Génération auto du code client
                            $lastId = \App\Models\Client::max('id') ?? 0;
                            $data['code'] = 'CLI-' . str_pad((string) ($lastId + 1), 4, '0', STR_PAD_LEFT);
                            $data['date_enregistrement'] = now()->toDateString();

                            return \App\Models\Client::create($data)->getKey();
                        }),

                    // Canal de vente (boutique, commercial terrain, livraison, B2B)
                    Forms\Components\Select::make('canal')
                        ->label('Canal de vente')
                        ->options(collect(Canal::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()]))
                        ->required()
                        ->default('boutique'),

                    // Emplacement (d'où part la marchandise)
                    Forms\Components\Select::make('emplacement_id')
                        ->label('Emplacement')
                        ->options(Emplacement::where('actif', true)->pluck('nom', 'id'))
                        ->required()
                        ->default(1),

                    // Commercial associé
                    Forms\Components\Select::make('commercial_id')
                        ->label('Commercial')
                        ->relationship('commercial', 'name')
                        ->searchable()
                        ->preload()
                        ->default(fn() => auth()->id()),
                ])->columns(2),

            // Section 2 : Lignes de vente (les produits vendus)
            Forms\Components\Section::make('Produits vendus')
                ->description('Ajoutez les produits, quantités et prix. Le montant se calcule automatiquement.')
                ->schema([
                    Forms\Components\Repeater::make('lignes')
                        ->label('')
                        ->relationship()
                        ->schema([
                            // Sélection du produit
                            Forms\Components\Select::make('produit_id')
                                ->label('Produit')
                                ->options(
                                    Produit::where('actif', true)->pluck('nom', 'id')
                                )
                                ->required()
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                    // Pré-remplir le prix de vente par défaut du produit
                                    if ($state) {
                                        $produit = Produit::find($state);
                                        if ($produit) {
                                            $set('prix_unitaire', (float) $produit->prix_vente_defaut);
                                            // Recalculer le montant de la ligne
                                            $qte = (float) ($get('quantite') ?? 0);
                                            $set('montant_ligne', round($qte * (float) $produit->prix_vente_defaut, 2));
                                        }
                                    }
                                }),

                            // Quantité vendue
                            Forms\Components\TextInput::make('quantite')
                                ->label('Quantité')
                                ->numeric()
                                ->required()
                                ->minValue(0.001)
                                ->step(0.001)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    // Recalcul du montant de la ligne
                                    $qte = (float) ($get('quantite') ?? 0);
                                    $prix = (float) ($get('prix_unitaire') ?? 0);
                                    $set('montant_ligne', round($qte * $prix, 2));
                                }),

                            // Prix unitaire (pré-rempli, modifiable)
                            Forms\Components\TextInput::make('prix_unitaire')
                                ->label('Prix unit. (FCFA)')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->suffix('FCFA')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    $qte = (float) ($get('quantite') ?? 0);
                                    $prix = (float) ($get('prix_unitaire') ?? 0);
                                    $set('montant_ligne', round($qte * $prix, 2));
                                }),

                            // Montant de la ligne (calculé auto, non modifiable)
                            Forms\Components\TextInput::make('montant_ligne')
                                ->label('Montant (FCFA)')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(true)
                                ->suffix('FCFA'),
                        ])
                        ->columns(4)
                        ->defaultItems(1)
                        ->addActionLabel('Ajouter un produit')
                        ->reorderable(false)
                        ->collapsible()
                        ->itemLabel(fn(array $state): ?string => $state['produit_id']
                            ? Produit::find($state['produit_id'])?->nom ?? 'Produit'
                            : 'Nouveau produit'
                        ),
                ]),

            // Section 3 : Totaux et paiement
            Forms\Components\Section::make('Totaux & Paiement')
                ->description('Montants calculés automatiquement. Renseignez le montant reçu pour le paiement.')
                ->schema([
                    // Montant total (somme des lignes) — affiché en lecture seule
                    Forms\Components\Placeholder::make('montant_total_display')
                        ->label('Montant total')
                        ->content(function (Get $get): string {
                            $lignes = $get('lignes') ?? [];
                            $total = collect($lignes)->sum(fn($l) => (float) ($l['montant_ligne'] ?? 0));

                            return number_format($total, 0, ',', ' ') . ' FCFA';
                        }),

                    // Remise accordée
                    Forms\Components\TextInput::make('remise')
                        ->label('Remise (FCFA)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->suffix('FCFA')
                        ->live(onBlur: true),

                    // Montant net affiché
                    Forms\Components\Placeholder::make('montant_net_display')
                        ->label('Montant net à payer')
                        ->content(function (Get $get): string {
                            $lignes = $get('lignes') ?? [];
                            $total = collect($lignes)->sum(fn($l) => (float) ($l['montant_ligne'] ?? 0));
                            $remise = (float) ($get('remise') ?? 0);

                            return number_format(max(0, $total - $remise), 0, ',', ' ') . ' FCFA';
                        }),

                    // Montant reçu du client
                    Forms\Components\TextInput::make('montant_recu')
                        ->label('Montant reçu (FCFA)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->suffix('FCFA')
                        ->live(onBlur: true)
                        ->helperText('Si inférieur au montant net, la vente sera en crédit'),

                    // Mode de paiement
                    Forms\Components\Select::make('mode_paiement')
                        ->label('Mode de paiement')
                        ->options(collect(ModePaiement::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()]))
                        ->required()
                        ->default('especes'),

                    // Statut paiement (affiché en lecture seule)
                    Forms\Components\Placeholder::make('statut_display')
                        ->label('Statut paiement')
                        ->content(function (Get $get): string {
                            $lignes = $get('lignes') ?? [];
                            $total = collect($lignes)->sum(fn($l) => (float) ($l['montant_ligne'] ?? 0));
                            $remise = (float) ($get('remise') ?? 0);
                            $net = max(0, $total - $remise);
                            $recu = (float) ($get('montant_recu') ?? 0);

                            if ($net <= 0) {
                                return '';
                            }
                            if ($recu <= 0) {
                                return 'Crédit total';
                            }
                            if ($recu < $net) {
                                $restant = $net - $recu;

                                return 'Partiel, reste : ' . number_format($restant, 0, ',', ' ') . ' FCFA';
                            }

                            return 'Payé intégralement';
                        }),
                ])->columns(2),

            // Section 4 : Observations
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
            ->modifyQueryUsing(fn($query) => $query->with(['client', 'emplacement', 'commercial']))
            ->defaultSort('date_vente', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('numero_recu')
                    ->label('N° Reçu')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Small)
                    // Affiche la date sous le numéro pour économiser une colonne
                    ->description(fn ($record) => $record->date_vente?->format('d/m/Y H:i')),

                Tables\Columns\TextColumn::make('client.nom')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->limit(20)
                    // Affiche le canal en description sous le nom client
                    ->description(fn ($record) => $record->canal?->getLabel()),

                Tables\Columns\TextColumn::make('montant_net')
                    ->label('Net')
                    ->numeric(0)
                    ->suffix(' F')
                    ->sortable()
                    ->weight('bold')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('montant_restant')
                    ->label('Restant')
                    ->numeric(0)
                    ->suffix(' F')
                    ->color(fn ($state) => (float) $state > 0 ? 'danger' : 'success')
                    ->alignEnd()
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('statut_paiement')
                    ->label('Statut')
                    ->badge(),

                // Colonnes masquées par défaut, affichables via le bouton colonnes
                Tables\Columns\TextColumn::make('date_vente')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('canal')
                    ->label('Canal')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('montant_recu')
                    ->label('Reçu')
                    ->numeric(0)
                    ->suffix(' F')
                    ->color('success')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('emplacement.nom')
                    ->label('Emplacement')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('commercial.name')
                    ->label('Commercial')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('annulee')
                    ->label('Annulée')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut_paiement')
                    ->label('Statut paiement')
                    ->options(collect(StatutPaiement::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()])),

                Tables\Filters\SelectFilter::make('canal')
                    ->label('Canal')
                    ->options(collect(Canal::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()])),

                Tables\Filters\SelectFilter::make('emplacement_id')
                    ->label('Emplacement')
                    ->relationship('emplacement', 'nom'),

                Tables\Filters\SelectFilter::make('commercial_id')
                    ->label('Commercial')
                    ->relationship('commercial', 'name'),

                // Filtre par date
                Tables\Filters\Filter::make('date_vente')
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
                            ->when($data['du'], fn($q) => $q->whereDate('date_vente', '>=', $data['du']))
                            ->when($data['au'], fn($q) => $q->whereDate('date_vente', '<=', $data['au']));
                    }),

                // Filtre ventes annulées
                Tables\Filters\TernaryFilter::make('annulee')
                    ->label('Annulée'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // Bouton pour imprimer le reçu PDF directement depuis la liste
                Tables\Actions\Action::make('imprimer')
                    ->label('Reçu')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn (Vente $record) => route('vente.recu-pdf', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                // Export Excel des ventes sélectionnées
                Tables\Actions\BulkAction::make('exporter')
                    ->label('Exporter Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        return response()->streamDownload(function () use ($records) {
                            $csv = implode(',', [
                                'N° Reçu', 'Date', 'Client', 'Canal',
                                'Montant Net', 'Reçu', 'Restant', 'Statut',
                            ]) . "\n";
                            foreach ($records as $vente) {
                                $csv .= implode(',', [
                                    $vente->numero_recu,
                                    $vente->date_vente->format('d/m/Y'),
                                    '"' . ($vente->client?->nom ?? '') . '"',
                                    $vente->canal?->getLabel() ?? '',
                                    (float) $vente->montant_net,
                                    (float) $vente->montant_recu,
                                    (float) $vente->montant_restant,
                                    $vente->statut_paiement?->getLabel() ?? '',
                                ]) . "\n";
                            }
                            echo $csv;
                        }, 'ventes-' . now()->format('Y-m-d') . '.csv', [
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
            'index'  => Pages\ListVentes::route('/'),
            'create' => Pages\CreateVente::route('/create'),
            'view'   => Pages\ViewVente::route('/{record}'),
            'edit'   => Pages\EditVente::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            VenteResource\Widgets\VenteStatsWidget::class,
        ];
    }
}
