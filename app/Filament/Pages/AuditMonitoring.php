<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\JournalAudit;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Page de surveillance & audit — accessible uniquement par le super_admin.
 *
 * Cette page offre une vue centralisée sur :
 * - Le journal d'audit (qui fait quoi, quand, depuis quelle IP)
 * - Les utilisateurs actuellement en ligne (sessions actives)
 *
 * L'interface utilise 2 onglets gérés par une propriété Livewire $activeTab.
 * Quand on change d'onglet, la table se recharge avec les données correspondantes.
 */
class AuditMonitoring extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-eye';
    protected static ?string $title = 'Surveillance & Audit';
    protected static ?string $slug = 'audit-monitoring';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.audit-monitoring';

    /**
     * Onglet actif : 'journal' ou 'en_ligne'.
     * Propriété Livewire — le changement déclenche un re-rendu de la table.
     */
    public string $activeTab = 'journal';

    /**
     * Restriction d'accès : seul le super_admin peut voir et accéder à cette page.
     * Pour tous les autres rôles, la page est invisible dans le menu
     * et inaccessible par URL directe (retourne 403).
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    /**
     * Quand l'onglet change, on réinitialise la table (pagination, filtres, tri)
     * pour éviter des incohérences entre les deux sources de données.
     */
    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    /**
     * Configuration de la table — change selon l'onglet actif.
     */
    public function table(Table $table): Table
    {
        return match ($this->activeTab) {
            'en_ligne' => $this->tableUtilisateursEnLigne($table),
            default    => $this->tableJournalAudit($table),
        };
    }

    // ─────────────────────────────────────────────────────────────
    // ONGLET 1 : Journal d'audit
    // ─────────────────────────────────────────────────────────────

    protected function tableJournalAudit(Table $table): Table
    {
        return $table
            ->query(
                JournalAudit::query()->with('utilisateur')
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date / Heure')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Small),

                Tables\Columns\TextColumn::make('utilisateur.name')
                    ->label('Utilisateur')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'creation'     => 'success',
                        'modification' => 'warning',
                        'suppression'  => 'danger',
                        'annulation'   => 'danger',
                        'validation'   => 'info',
                        'connexion'    => 'primary',
                        'deconnexion'  => 'gray',
                        default        => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('table_concernee')
                    ->label('Table')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('enregistrement_id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('adresse_ip')
                    ->label('Adresse IP')
                    ->sortable()
                    ->visibleFrom('md'),
            ])
            ->filters([
                // Filtre par type d'action
                Tables\Filters\SelectFilter::make('action')
                    ->label('Action')
                    ->options([
                        'creation'     => 'Création',
                        'modification' => 'Modification',
                        'suppression'  => 'Suppression',
                        'annulation'   => 'Annulation',
                        'validation'   => 'Validation',
                        'connexion'    => 'Connexion',
                        'deconnexion'  => 'Déconnexion',
                    ]),

                // Filtre par utilisateur
                Tables\Filters\SelectFilter::make('utilisateur_id')
                    ->label('Utilisateur')
                    ->relationship('utilisateur', 'name')
                    ->searchable()
                    ->preload(),

                // Filtre par table concernée
                Tables\Filters\SelectFilter::make('table_concernee')
                    ->label('Table')
                    ->options(fn () => JournalAudit::query()
                        ->distinct()
                        ->pluck('table_concernee', 'table_concernee')
                        ->toArray()
                    ),

                // Filtre par période (du ... au ...)
                Tables\Filters\Filter::make('periode')
                    ->form([
                        DatePicker::make('du')
                            ->label('Du')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('au')
                            ->label('Au')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['du'], fn ($q) => $q->whereDate('created_at', '>=', $data['du']))
                            ->when($data['au'], fn ($q) => $q->whereDate('created_at', '<=', $data['au']));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['du'] ?? null) {
                            $indicators['du'] = 'Du ' . Carbon::parse($data['du'])->format('d/m/Y');
                        }
                        if ($data['au'] ?? null) {
                            $indicators['au'] = 'Au ' . Carbon::parse($data['au'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                // Action "Voir" pour afficher les données avant/après dans une modale
                Tables\Actions\Action::make('voir_details')
                    ->label('Détails')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Détail de l\'action')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->infolist([
                        Section::make('Informations')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('utilisateur.name')
                                    ->label('Utilisateur')
                                    ->weight('bold'),
                                TextEntry::make('action')
                                    ->label('Action')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'creation'     => 'success',
                                        'modification' => 'warning',
                                        'suppression'  => 'danger',
                                        'annulation'   => 'danger',
                                        'validation'   => 'info',
                                        'connexion'    => 'primary',
                                        'deconnexion'  => 'gray',
                                        default        => 'gray',
                                    }),
                                TextEntry::make('table_concernee')
                                    ->label('Table'),
                                TextEntry::make('enregistrement_id')
                                    ->label('ID enregistrement'),
                                TextEntry::make('adresse_ip')
                                    ->label('Adresse IP'),
                                TextEntry::make('created_at')
                                    ->label('Date / Heure')
                                    ->dateTime('d/m/Y H:i:s'),
                            ]),

                        Section::make('Données AVANT')
                            ->collapsed()
                            ->schema([
                                TextEntry::make('donnees_avant')
                                    ->label('')
                                    ->formatStateUsing(function ($state) {
                                        if (empty($state)) {
                                            return 'Aucune donnée (création ou connexion)';
                                        }
                                        return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                    })
                                    ->markdown()
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Données APRÈS')
                            ->collapsed()
                            ->schema([
                                TextEntry::make('donnees_apres')
                                    ->label('')
                                    ->formatStateUsing(function ($state) {
                                        if (empty($state)) {
                                            return 'Aucune donnée (suppression)';
                                        }
                                        return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                    })
                                    ->markdown()
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ])
            ->bulkActions([])
            ->poll('60s');
    }

    // ─────────────────────────────────────────────────────────────
    // ONGLET 2 : Utilisateurs en ligne
    // ─────────────────────────────────────────────────────────────

    protected function tableUtilisateursEnLigne(Table $table): Table
    {
        // Seuil : actif dans les 15 dernières minutes
        $seuilEnLigne = Carbon::now()->subMinutes(15)->getTimestamp();

        return $table
            ->query(
                User::query()
                    ->whereExists(function ($query) use ($seuilEnLigne) {
                        $query->select(DB::raw(1))
                            ->from('sessions')
                            ->whereColumn('sessions.user_id', 'users.id')
                            ->where('sessions.last_activity', '>=', $seuilEnLigne);
                    })
                    ->with(['emplacement'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Utilisateur')
                    ->searchable()
                    ->weight('bold')
                    ->icon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('profil')
                    ->label('Profil')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? '') {
                        'gerant'             => 'danger',
                        'resp_operations'    => 'warning',
                        'gestionnaire_stock' => 'info',
                        'agent_production'   => 'success',
                        'commercial', 'point_de_vente' => 'primary',
                        default              => 'gray',
                    }),

                Tables\Columns\TextColumn::make('emplacement.nom')
                    ->label('Emplacement')
                    ->visibleFrom('md'),

                // IP récupérée depuis la table sessions
                Tables\Columns\TextColumn::make('session_ip')
                    ->label('Adresse IP')
                    ->getStateUsing(function (User $record): string {
                        return DB::table('sessions')
                            ->where('user_id', $record->id)
                            ->orderByDesc('last_activity')
                            ->value('ip_address') ?? '';
                    }),

                // Dernière activité depuis la table sessions
                Tables\Columns\TextColumn::make('derniere_activite')
                    ->label('Dernière activité')
                    ->getStateUsing(function (User $record): string {
                        $timestamp = DB::table('sessions')
                            ->where('user_id', $record->id)
                            ->orderByDesc('last_activity')
                            ->value('last_activity');

                        if (! $timestamp) {
                            return '';
                        }

                        return Carbon::createFromTimestamp($timestamp)
                            ->diffForHumans();
                    })
                    ->description(function (User $record): string {
                        $timestamp = DB::table('sessions')
                            ->where('user_id', $record->id)
                            ->orderByDesc('last_activity')
                            ->value('last_activity');

                        if (! $timestamp) {
                            return '';
                        }

                        return Carbon::createFromTimestamp($timestamp)
                            ->format('d/m/Y H:i:s');
                    }),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('Aucun utilisateur en ligne')
            ->emptyStateDescription('Personne n\'est connecté actuellement.')
            ->emptyStateIcon('heroicon-o-user-minus')
            ->actions([])
            ->bulkActions([])
            ->poll('30s');
    }
}
