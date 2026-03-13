<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Vente;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel des ventes.
 *
 * Peut être filtré par dates via le constructeur.
 * Inclut : numéro reçu, date, client, commercial, canal,
 * montants (total, remise, net, reçu, restant), statut paiement.
 */
class VentesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        private ?string $dateDebut = null,
        private ?string $dateFin = null,
    ) {}

    public function query(): Builder
    {
        return Vente::query()
            ->with(['client', 'commercial', 'emplacement'])
            ->where('annulee', false)
            ->when($this->dateDebut, fn($q) => $q->whereDate('date_vente', '>=', $this->dateDebut))
            ->when($this->dateFin, fn($q) => $q->whereDate('date_vente', '<=', $this->dateFin))
            ->orderByDesc('date_vente');
    }

    public function headings(): array
    {
        return [
            'N° Reçu',
            'Date',
            'Client',
            'Commercial',
            'Emplacement',
            'Canal',
            'Montant Total',
            'Remise',
            'Montant Net',
            'Montant Reçu',
            'Montant Restant',
            'Statut Paiement',
            'Mode Paiement',
        ];
    }

    /**
     * @param Vente $vente
     */
    public function map($vente): array
    {
        return [
            $vente->numero_recu,
            $vente->date_vente->format('d/m/Y'),
            $vente->client?->nom ?? '—',
            $vente->commercial?->name ?? '—',
            $vente->emplacement?->nom ?? '—',
            $vente->canal?->getLabel() ?? '—',
            (float) $vente->montant_total,
            (float) $vente->remise,
            (float) $vente->montant_net,
            (float) $vente->montant_recu,
            (float) $vente->montant_restant,
            $vente->statut_paiement?->getLabel() ?? '—',
            $vente->mode_paiement?->getLabel() ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
