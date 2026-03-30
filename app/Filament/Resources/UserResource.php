<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\Profil;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Emplacement;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Resource Filament pour la gestion des utilisateurs.
 *
 * Chaque utilisateur a un "profil" (enum PHP) qui détermine son rôle métier.
 * À la création/modification, le rôle Spatie correspondant est assigné
 * automatiquement dans les pages Create/Edit.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $modelLabel = 'Utilisateur';

    protected static ?string $pluralModelLabel = 'Utilisateurs';

    protected static ?int $navigationSort = 1;

    // Cache le module du menu de navigation pour tous les non-super_admin
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Identité')
                ->icon('heroicon-o-user')
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label('Nom complet')->weight('bold'),
                    Infolists\Components\TextEntry::make('email')->label('Email')->copyable(),
                    Infolists\Components\TextEntry::make('telephone')->label('Téléphone')->placeholder('—'),
                    Infolists\Components\TextEntry::make('profil')->label('Profil')->badge()
                        ->color(fn (Profil $state) => match ($state) {
                            Profil::GERANT => 'danger',
                            Profil::RESP_OPERATIONS => 'warning',
                            Profil::GESTIONNAIRE_STOCK => 'info',
                            Profil::AGENT_PRODUCTION => 'success',
                            Profil::COMMERCIAL, Profil::POINT_DE_VENTE => 'primary',
                        }),
                    Infolists\Components\TextEntry::make('emplacement.nom')->label('Emplacement')->icon('heroicon-o-map-pin')->placeholder('—'),
                    Infolists\Components\IconEntry::make('actif')->label('Actif')->boolean(),
                ])->columns(3),

            Infolists\Components\Section::make('Rôle & Permissions')
                ->icon('heroicon-o-shield-check')
                ->schema([
                    Infolists\Components\TextEntry::make('roles.name')
                        ->label('Rôle(s) Spatie')
                        ->badge()
                        ->color('success')
                        ->placeholder('Aucun rôle'),
                    Infolists\Components\TextEntry::make('created_at')->label('Créé le')->dateTime('d/m/Y H:i'),
                    Infolists\Components\TextEntry::make('updated_at')->label('Modifié le')->dateTime('d/m/Y H:i'),
                ])->columns(3)
                ->collapsible(),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Section 1 : Informations de l'utilisateur
            Forms\Components\Section::make('Informations de l\'utilisateur')
                ->description('Identité et accès au système')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nom complet')
                        ->required()
                        ->maxLength(100),

                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(150),

                    Forms\Components\TextInput::make('telephone')
                        ->label('Téléphone')
                        ->mask('9999999999')
                        ->placeholder('0700000000'),

                    Forms\Components\TextInput::make('password')
                        ->label('Mot de passe')
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->minLength(6)
                        ->helperText(fn (string $operation) => $operation === 'edit'
                            ? 'Laissez vide pour ne pas changer le mot de passe'
                            : 'Minimum 6 caractères')
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('generer')
                                ->icon('heroicon-o-key')
                                ->tooltip('Générer un mot de passe aléatoire')
                                ->action(function (Set $set) {
                                    // Génère un mot de passe lisible de 10 caractères
                                    $set('password', substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, 10));
                                })
                        ),
                ])->columns(2),

            // Section 2 : Profil et emplacement
            Forms\Components\Section::make('Profil & Emplacement')
                ->description('Le rôle et les permissions seront assignés automatiquement selon le profil choisi')
                ->schema([
                    Forms\Components\Select::make('profil')
                        ->label('Profil')
                        ->options(collect(Profil::cases())->mapWithKeys(
                            fn ($e) => [$e->value => $e->getLabel()]
                        ))
                        ->required()
                        ->helperText('Détermine les droits d\'accès de l\'utilisateur'),

                    Forms\Components\Select::make('emplacement_id')
                        ->label('Emplacement par défaut')
                        ->options(Emplacement::where('actif', true)->pluck('nom', 'id'))
                        ->helperText('Site de travail principal'),

                    Forms\Components\Toggle::make('actif')
                        ->label('Compte actif')
                        ->default(true)
                        ->helperText('Un compte inactif ne peut plus se connecter'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['emplacement', 'roles']))
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('telephone')
                    ->label('Téléphone')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('profil')
                    ->label('Profil')
                    ->badge()
                    ->color(fn (Profil $state) => match ($state) {
                        Profil::GERANT => 'danger',
                        Profil::RESP_OPERATIONS => 'warning',
                        Profil::GESTIONNAIRE_STOCK => 'info',
                        Profil::AGENT_PRODUCTION => 'success',
                        Profil::COMMERCIAL, Profil::POINT_DE_VENTE => 'primary',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('emplacement.nom')
                    ->label('Emplacement')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Rôle')
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('profil')
                    ->label('Profil')
                    ->options(collect(Profil::cases())->mapWithKeys(
                        fn ($e) => [$e->value => $e->getLabel()]
                    )),

                Tables\Filters\SelectFilter::make('emplacement_id')
                    ->label('Emplacement')
                    ->relationship('emplacement', 'nom'),

                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Actif'),
            ])
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
