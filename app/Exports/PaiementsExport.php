<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Paiement;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel des paiements (encaissements sur ventes).
 *
 * Colonnes : N° reçu, client, date paiement, montant,
 * mode paiement, référence, encaissé par.
 */
class PaiementsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        private ?string $dateDebut = null,
        private ?string $dateFin = null,
    ) {}

    public function query(): Builder
    {
        return Paiement::query()
            ->with(['vente.client', 'createdBy'])
            ->when($this->dateDebut, fn($q) => $q->whereDate('date_paiement', '>=', $this->dateDebut))
            ->when($this->dateFin, fn($q) => $q->whereDate('date_paiement', '<=', $this->dateFin))
            ->orderByDesc('date_paiement');
    }

    public function headings(): array
    {
        return [
            'N° Reçu',
            'Client',
            'Date paiement',
            'Montant (FCFA)',
            'Mode paiement',
            'Référence paiement',
            'Observations',
            'Encaissé par',
        ];
    }

    /**
     * @param Paiement $paiement
     */
    public function map($paiement): array
    {
        return [
            $paiement->vente?->numero_recu ?? '—',
            $paiement->vente?->client?->nom ?? '—',
            $paiement->date_paiement->format('d/m/Y H:i'),
            (float) $paiement->montant,
            $paiement->mode_paiement?->getLabel() ?? '—',
            $paiement->reference_paiement ?? '—',
            $paiement->observations ?? '—',
            $paiement->createdBy?->name ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
