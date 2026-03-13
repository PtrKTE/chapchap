<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Charge;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel des charges / dépenses.
 *
 * Peut être filtré par dates. Inclut : date, catégorie, libellé,
 * montant, mode paiement, emplacement.
 */
class ChargesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        private ?string $dateDebut = null,
        private ?string $dateFin = null,
    ) {}

    public function query(): Builder
    {
        return Charge::query()
            ->with(['categorie', 'emplacement', 'fournisseur', 'createdBy'])
            ->when($this->dateDebut, fn($q) => $q->whereDate('date_charge', '>=', $this->dateDebut))
            ->when($this->dateFin, fn($q) => $q->whereDate('date_charge', '<=', $this->dateFin))
            ->orderByDesc('date_charge');
    }

    public function headings(): array
    {
        return [
            'Date',
            'Catégorie',
            'Type',
            'Libellé',
            'Montant (FCFA)',
            'Mode Paiement',
            'Emplacement',
            'Fournisseur',
            'Personne',
            'Saisi par',
        ];
    }

    /**
     * @param Charge $charge
     */
    public function map($charge): array
    {
        return [
            $charge->date_charge->format('d/m/Y'),
            $charge->categorie?->nom ?? '—',
            $charge->categorie?->type?->getLabel() ?? '—',
            $charge->libelle,
            (float) $charge->montant,
            $charge->mode_paiement?->getLabel() ?? '—',
            $charge->emplacement?->nom ?? '—',
            $charge->fournisseur?->nom ?? '—',
            $charge->personne ?? '—',
            $charge->createdBy?->name ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
