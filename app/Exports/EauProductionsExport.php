<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\EauProduction;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel des productions d'eau en sachet.
 */
class EauProductionsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        private ?string $dateDebut = null,
        private ?string $dateFin = null,
    ) {}

    public function query(): Builder
    {
        return EauProduction::query()
            ->with('createdBy')
            ->when($this->dateDebut, fn($q) => $q->whereDate('date_production', '>=', $this->dateDebut))
            ->when($this->dateFin, fn($q) => $q->whereDate('date_production', '<=', $this->dateFin))
            ->orderByDesc('date_production');
    }

    public function headings(): array
    {
        return [
            'Date',
            'Paquets produits',
            'Sachets consommés',
            'Énergie (kWh)',
            'Observations',
            'Saisi par',
        ];
    }

    /**
     * @param EauProduction $eau
     */
    public function map($eau): array
    {
        return [
            $eau->date_production->format('d/m/Y'),
            $eau->nb_paquets_produits,
            $eau->consommation_sachets ?? 0,
            (float) $eau->consommation_energie,
            $eau->observations ?? '—',
            $eau->createdBy?->name ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
