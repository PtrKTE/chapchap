<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Lot;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel des lots / achats.
 *
 * Inclut : référence, date réception, fournisseur, quantités,
 * coûts, statut facture et montant réglé.
 */
class LotsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        private ?string $dateDebut = null,
        private ?string $dateFin = null,
    ) {}

    public function query(): Builder
    {
        return Lot::query()
            ->with(['fournisseur'])
            ->when($this->dateDebut, fn($q) => $q->whereDate('date_reception', '>=', $this->dateDebut))
            ->when($this->dateFin, fn($q) => $q->whereDate('date_reception', '<=', $this->dateFin))
            ->orderByDesc('date_reception');
    }

    public function headings(): array
    {
        return [
            'Référence',
            'Date Réception',
            'Fournisseur',
            'Type',
            'Qté Reçue',
            'Morts (PM)',
            'Refusés (IR)',
            'Qté Utilisable',
            'Prix Unitaire',
            'Coût Transport',
            'Coût Total',
            'CMP Unitaire',
            'Montant Facture',
            'Statut Facture',
            'Montant Réglé',
        ];
    }

    /**
     * @param Lot $lot
     */
    public function map($lot): array
    {
        return [
            $lot->reference,
            $lot->date_reception->format('d/m/Y'),
            $lot->fournisseur?->nom ?? '—',
            $lot->type_produit ?? '—',
            $lot->quantite_recue,
            $lot->quantite_morts,
            $lot->quantite_refuses,
            $lot->quantite_utilisable,
            (float) $lot->prix_unitaire,
            (float) $lot->cout_transport,
            (float) $lot->cout_total,
            (float) $lot->cout_moyen_unitaire,
            (float) $lot->montant_facture,
            $lot->statut_facture?->getLabel() ?? '—',
            (float) $lot->montant_regle,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
