<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ModePaiement;
use App\Enums\TypeClient;
use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resource Filament pour la gestion des clients (CRM).
 *
 * Enrichie en S6 avec :
 * - Solde impayé calculé (somme des montants restants des ventes non annulées)
 * - Nombre de ventes par client
 * - Filtres avancés (type client, commercial, actif/inactif, impayés)
 */
class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Commercial';

    protected static ?string $modelLabel = 'Client';

    protected static ?string $pluralModelLabel = 'Clients';

    protected static ?int $navigationSort = 1;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Identification')
                ->icon('heroicon-o-user')
                ->schema([
                    Infolists\Components\TextEntry::make('code')->label('Code')->weight('bold')->copyable(),
                    Infolists\Components\TextEntry::make('nom')->label('Nom / Raison sociale')->weight('bold'),
                    Infolists\Components\TextEntry::make('type_client')->label('Type')->badge(),
                    Infolists\Components\TextEntry::make('telephone')->label('Téléphone')->icon('heroicon-o-phone')->placeholder('—'),
                    Infolists\Components\TextEntry::make('email')->label('Email')->icon('heroicon-o-envelope')->placeholder('—'),
                    Infolists\Components\IconEntry::make('actif')->label('Actif')->boolean(),
                ])->columns(3),

            Infolists\Components\Section::make('Localisation & Commercial')
                ->icon('heroicon-o-map-pin')
                ->schema([
                    Infolists\Components\TextEntry::make('adresse')->label('Adresse')->placeholder('—'),
                    Infolists\Components\TextEntry::make('quartier_zone')->label('Quartier / Zone')->placeholder('—'),
                    Infolists\Components\TextEntry::make('commercial.name')->label('Commercial')->icon('heroicon-o-briefcase')->placeholder('—'),
                    Infolists\Components\TextEntry::make('mode_paiement_habituel')->label('Mode paiement')->badge()->placeholder('—'),
                    Infolists\Components\TextEntry::make('contact_principal')->label('Contact')->placeholder('—'),
                    Infolists\Components\TextEntry::make('conditions_paiement')->label('Conditions')->placeholder('—'),
                ])->columns(3)
                ->collapsible(),

            Infolists\Components\Section::make('Notes')
                ->schema([
                    Infolists\Components\TextEntry::make('notes')->label('Notes')->placeholder('Aucune note')->columnSpanFull(),
                ])->collapsible(),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Section 1 : Identification du client
            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Code client')
                        ->disabled()
                        ->dehydrated(false)
                        ->hiddenOn('create')
                        ->helperText('Généré automatiquement'),
                    Forms\Components\TextInput::make('nom')
                        ->label('Nom / Raison sociale')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\Select::make('type_client')
                        ->label('Type de client')
                        ->options(collect(TypeClient::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()]))
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
                ])->columns(2),

            // Section 2 : Adresse
            Forms\Components\Section::make('Adresse & Localisation')
                ->schema([
                    Forms\Components\Textarea::make('adresse')
                        ->label('Adresse')
                        ->rows(2),
                    Forms\Components\TextInput::make('quartier_zone')
                        ->label('Quartier / Zone')
                        ->maxLength(100),
                ])->columns(2),

            // Section 3 : Infos commerciales
            Forms\Components\Section::make('Contact & Commercial')
                ->schema([
                    Forms\Components\TextInput::make('secteur_activite')
                        ->label('Secteur d\'activité')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('contact_principal')
                        ->label('Contact principal')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('fonction_contact')
                        ->label('Fonction du contact')
                        ->maxLength(100),
                    Forms\Components\Select::make('commercial_id')
                        ->label('Commercial attitré')
                        ->relationship('commercial', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('mode_paiement_habituel')
                        ->label('Mode de paiement habituel')
                        ->options(collect(ModePaiement::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()])),
                    Forms\Components\TextInput::make('conditions_paiement')
                        ->label('Conditions de paiement')
                        ->maxLength(100)
                        ->helperText('Ex: 30 jours fin de mois, paiement à livraison'),
                ])->columns(2),

            // Section 4 : Divers
            Forms\Components\Section::make('Divers')
                ->schema([
                    Forms\Components\Toggle::make('actif')
                        ->label('Actif')
                        ->default(true),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nom')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('nom')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('type_client')
                    ->label('Type')
                    ->badge(),

                Tables\Columns\TextColumn::make('telephone')
                    ->label('Téléphone')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('commercial.name')
                    ->label('Commercial')
                    ->toggleable()
                    ->visibleFrom('md'),

                // Nombre de ventes du client (calculé dynamiquement)
                Tables\Columns\TextColumn::make('ventes_count')
                    ->label('Nb ventes')
                    ->counts('ventes')
                    ->sortable()
                    ->visibleFrom('md'),

                // Solde impayé : somme des montants restants des ventes non annulées
                Tables\Columns\TextColumn::make('solde_impaye')
                    ->label('Impayés')
                    ->getStateUsing(function (Client $record): string {
                        $solde = $record->ventes()
                            ->where('annulee', false)
                            ->where('montant_restant', '>', 0)
                            ->sum('montant_restant');

                        return number_format((float) $solde, 0, ',', ' ') . ' FCFA';
                    })
                    ->color(fn(Client $record): string =>
                        $record->ventes()->where('annulee', false)->where('montant_restant', '>', 0)->exists()
                            ? 'danger'
                            : 'success'
                    )
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('quartier_zone')
                    ->label('Zone')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type_client')
                    ->label('Type')
                    ->options(collect(TypeClient::cases())->mapWithKeys(fn($e) => [$e->value => $e->getLabel()])),

                Tables\Filters\SelectFilter::make('commercial_id')
                    ->label('Commercial')
                    ->relationship('commercial', 'name'),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif'),

                // Filtre pour voir uniquement les clients avec des impayés
                Tables\Filters\Filter::make('avec_impayes')
                    ->label('Avec impayés')
                    ->query(fn(Builder $query) => $query->whereHas('ventes', function ($q) {
                        $q->where('annulee', false)->where('montant_restant', '>', 0);
                    }))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Export CSV des clients sélectionnés
                Tables\Actions\BulkAction::make('exporter')
                    ->label('Exporter Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        return response()->streamDownload(function () use ($records) {
                            $csv = implode(',', [
                                'Code', 'Nom', 'Téléphone', 'Type', 'Zone', 'Commercial', 'Actif',
                            ]) . "\n";
                            foreach ($records as $client) {
                                $csv .= implode(',', [
                                    $client->code,
                                    '"' . $client->nom . '"',
                                    $client->telephone ?? '—',
                                    $client->type_client?->getLabel() ?? '—',
                                    '"' . ($client->quartier_zone ?? '—') . '"',
                                    '"' . ($client->commercial?->name ?? '—') . '"',
                                    $client->actif ? 'Oui' : 'Non',
                                ]) . "\n";
                            }
                            echo $csv;
                        }, 'clients-' . now()->format('Y-m-d') . '.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    })
                    ->deselectRecordsAfterCompletion(),

                // Désactiver les clients sélectionnés
                Tables\Actions\BulkAction::make('desactiver')
                    ->label('Désactiver')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $records->each(fn(Client $client) => $client->update(['actif' => false]));

                        \Filament\Notifications\Notification::make()
                            ->title($records->count() . ' client(s) désactivé(s)')
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
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
