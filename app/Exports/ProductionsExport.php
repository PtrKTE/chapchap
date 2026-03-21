<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Production;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel des productions (abattage / découpe).
 *
 * Colonnes : référence, date, lot, nb poulets traités,
 * poids total entrant, pertes, rendement, statut, validé par, créé par.
 */
class ProductionsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        private ?string $dateDebut = null,
        private ?string $dateFin = null,
    ) {}

    public function query(): Builder
    {
        return Production::query()
            ->with(['lot', 'createdBy', 'validePar'])
            ->when($this->dateDebut, fn($q) => $q->whereDate('date_production', '>=', $this->dateDebut))
            ->when($this->dateFin, fn($q) => $q->whereDate('date_production', '<=', $this->dateFin))
            ->orderByDesc('date_production');
    }

    public function headings(): array
    {
        return [
            'Référence',
            'Date',
            'Lot',
            'Nb poulets traités',
            'Poids total entrant (kg)',
            'Pertes / Casse (kg)',
            'Rendement (%)',
            'Statut',
            'Validé par',
            'Créé par',
        ];
    }

    /**
     * @param Production $production
     */
    public function map($production): array
    {
        return [
            $production->reference,
            $production->date_production->format('d/m/Y'),
            $production->lot?->reference ?? '—',
            $production->nb_poulets_traites,
            (float) $production->poids_total_entrant,
            (float) $production->pertes_casse,
            $production->rendement ? round((float) $production->rendement * 100, 2) : '—',
            $production->statut?->getLabel() ?? '—',
            $production->validePar?->name ?? '—',
            $production->createdBy?->name ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
