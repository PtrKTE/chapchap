<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventaireResource\Pages;

use App\Filament\Resources\InventaireResource;
use App\Models\Inventaire;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListInventaires extends ListRecords
{
    protected static string $resource = InventaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nouvel inventaire'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'tous' => Tab::make('Tous')
                ->badge(Inventaire::count()),

            'en_cours' => Tab::make('En cours')
                ->badge(Inventaire::where('statut', 'en_cours')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('statut', 'en_cours')),

            'termines' => Tab::make('Terminés')
                ->badge(Inventaire::where('statut', 'termine')->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('statut', 'termine')),

            'valides' => Tab::make('Validés')
                ->badge(Inventaire::where('statut', 'valide')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('statut', 'valide')),
        ];
    }
}
