<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransfertResource\Pages;

use App\Filament\Resources\TransfertResource;
use App\Models\Transfert;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTransferts extends ListRecords
{
    protected static string $resource = TransfertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nouveau transfert'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'tous' => Tab::make('Tous')
                ->badge(Transfert::count()),

            'en_cours' => Tab::make('En cours')
                ->badge(Transfert::where('statut', 'en_cours')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('statut', 'en_cours')),

            'recus' => Tab::make('Reçus')
                ->badge(Transfert::where('statut', 'recu')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('statut', 'recu')),

            'annules' => Tab::make('Annulés')
                ->badge(Transfert::where('statut', 'annule')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('statut', 'annule')),
        ];
    }
}
