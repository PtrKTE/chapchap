<?php

declare(strict_types=1);

namespace App\Filament\Resources\CaisseResource\Pages;

use App\Enums\StatutCaisse;
use App\Filament\Resources\CaisseResource;
use App\Models\Caisse;
use App\Models\Charge;
use App\Models\Paiement;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * Page de création (ouverture) d'une caisse.
 *
 * À l'ouverture, on peut pré-calculer les totaux du jour
 * à partir des paiements et charges déjà enregistrés.
 */
class CreateCaisse extends CreateRecord
{
    protected static string $resource = CaisseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Statut initial : ouverte
        $data['statut'] = StatutCaisse::OUVERTE->value;

        // Calculer le solde et l'écart
        $encaisse = (float) ($data['total_encaisse'] ?? 0);
        $credits = (float) ($data['total_credits_encaisses'] ?? 0);
        $decaisse = (float) ($data['total_decaisse'] ?? 0);
        $solde = $encaisse + $credits - $decaisse;

        $verse = (float) ($data['montant_verse'] ?? 0);
        $wave = (float) ($data['depot_wave_mtn'] ?? 0);
        $cheque = (float) ($data['depot_cheque'] ?? 0);
        $ecart = $solde - ($verse + $wave + $cheque);

        $data['solde_caisse'] = round($solde, 2);
        $data['ecart'] = round($ecart, 2);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
