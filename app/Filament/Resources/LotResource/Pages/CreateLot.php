<?php

declare(strict_types=1);

namespace App\Filament\Resources\LotResource\Pages;

use App\Filament\Resources\LotResource;
use App\Models\Lot;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreateLot extends CreateRecord
{
    protected static string $resource = LotResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Générer la référence automatiquement : LOT-YYYYMMDD-XXX
        $date = Carbon::parse($data['date_reception'] ?? now())->format('Ymd');
        $dernierNumero = Lot::where('reference', 'like', "LOT-{$date}-%")->count() + 1;
        $data['reference'] = sprintf('LOT-%s-%03d', $date, $dernierNumero);

        // Utilisateur connecté
        $data['created_by'] = auth()->id();

        // Recalculer les valeurs pour s'assurer de la cohérence
        $recue = (int) ($data['quantite_recue'] ?? 0);
        $morts = (int) ($data['quantite_morts'] ?? 0);
        $refuses = (int) ($data['quantite_refuses'] ?? 0);
        $casses = (int) ($data['oeufs_casses'] ?? 0);

        $data['quantite_utilisable'] = max(0, $recue - $morts - $refuses - $casses);

        $coutTotal = ($recue * (float) ($data['prix_unitaire'] ?? 0))
            + (float) ($data['cout_transport'] ?? 0)
            + (float) ($data['autres_couts'] ?? 0);
        $data['cout_total'] = round($coutTotal, 2);

        $data['cout_moyen_unitaire'] = $data['quantite_utilisable'] > 0
            ? round($coutTotal / $data['quantite_utilisable'], 4)
            : 0;

        // Statut facture
        $facture = (float) ($data['montant_facture'] ?? 0);
        $regle = (float) ($data['montant_regle'] ?? 0);
        if ($facture <= 0 || $regle <= 0) {
            $data['statut_facture'] = 'non_reglee';
        } elseif ($regle < $facture) {
            $data['statut_facture'] = 'partielle';
        } else {
            $data['statut_facture'] = 'reglee';
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Lot créé avec succès';
    }
}
