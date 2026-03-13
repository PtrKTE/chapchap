<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\StockEmplacement;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel du stock actuel.
 *
 * Liste toutes les positions de stock (produit × emplacement)
 * avec quantité, CMP et valeur stock.
 */
class StockExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function query(): Builder
    {
        return StockEmplacement::query()
            ->with(['produit', 'emplacement'])
            ->where('quantite', '>', 0)
            ->orderBy('produit_id');
    }

    public function headings(): array
    {
        return [
            'Code Produit',
            'Produit',
            'Catégorie',
            'Emplacement',
            'Quantité',
            'Unité',
            'CMP (FCFA)',
            'Valeur Stock (FCFA)',
            'Seuil Alerte',
            'Dernière Entrée',
            'Dernière Sortie',
        ];
    }

    /**
     * @param StockEmplacement $stock
     */
    public function map($stock): array
    {
        return [
            $stock->produit?->code ?? '—',
            $stock->produit?->nom ?? '—',
            $stock->produit?->categorie?->getLabel() ?? '—',
            $stock->emplacement?->nom ?? '—',
            (float) $stock->quantite,
            $stock->produit?->unite_stock ?? '—',
            (float) $stock->cout_moyen_pondere,
            (float) $stock->valeur_stock,
            (float) ($stock->produit?->seuil_alerte_stock ?? 0),
            $stock->derniere_entree?->format('d/m/Y H:i') ?? '—',
            $stock->derniere_sortie?->format('d/m/Y H:i') ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
