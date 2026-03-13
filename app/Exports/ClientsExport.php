<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel de la liste des clients.
 *
 * Inclut les infos de contact, le type, le commercial assigné,
 * le nombre de ventes et le solde impayé.
 */
class ClientsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function query(): Builder
    {
        return Client::query()
            ->with(['commercial'])
            ->withCount('ventes')
            ->withSum(
                ['ventes as solde_impaye' => fn($q) => $q->where('annulee', false)->where('montant_restant', '>', 0)],
                'montant_restant'
            )
            ->where('actif', true)
            ->orderBy('nom');
    }

    public function headings(): array
    {
        return [
            'Code',
            'Nom',
            'Téléphone',
            'Email',
            'Quartier/Zone',
            'Type Client',
            'Commercial',
            'Mode Paiement Habituel',
            'Nb Ventes',
            'Solde Impayé (FCFA)',
            'Date Enregistrement',
        ];
    }

    /**
     * @param Client $client
     */
    public function map($client): array
    {
        return [
            $client->code,
            $client->nom,
            $client->telephone ?? '—',
            $client->email ?? '—',
            $client->quartier_zone ?? '—',
            $client->type_client?->getLabel() ?? '—',
            $client->commercial?->name ?? '—',
            $client->mode_paiement_habituel?->getLabel() ?? '—',
            $client->ventes_count ?? 0,
            (float) ($client->solde_impaye ?? 0),
            $client->date_enregistrement?->format('d/m/Y') ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
