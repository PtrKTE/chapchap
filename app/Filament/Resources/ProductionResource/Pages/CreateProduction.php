<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionResource\Pages;

use App\Filament\Resources\ProductionResource;
use App\Models\Production;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreateProduction extends CreateRecord
{
    protected static string $resource = ProductionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Générer la référence : PROD-YYYYMMDD-XXX
        $date = Carbon::parse($data['date_production'] ?? now())->format('Ymd');
        $count = Production::where('reference', 'like', "PROD-{$date}-%")->count() + 1;
        $data['reference'] = sprintf('PROD-%s-%03d', $date, $count);

        // Utilisateur connecté
        $data['created_by'] = auth()->id();

        // Statut initial
        $data['statut'] = 'en_cours';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        // Rediriger vers l'édition pour pouvoir valider
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Production créée — pensez à la valider pour générer le stock';
    }
}
