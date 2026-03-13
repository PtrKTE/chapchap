<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Caisse;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel du journal de caisse.
 *
 * Inclut : date, emplacement, tous les montants (encaissé, décaissé,
 * versé, dépôts Wave/MTN, chèque), solde, écart, statut.
 */
class CaissesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        private ?string $dateDebut = null,
        private ?string $dateFin = null,
    ) {}

    public function query(): Builder
    {
        return Caisse::query()
            ->with(['emplacement', 'responsable'])
            ->when($this->dateDebut, fn($q) => $q->whereDate('date_caisse', '>=', $this->dateDebut))
            ->when($this->dateFin, fn($q) => $q->whereDate('date_caisse', '<=', $this->dateFin))
            ->orderByDesc('date_caisse');
    }

    public function headings(): array
    {
        return [
            'Date',
            'Emplacement',
            'Responsable',
            'Total Encaissé',
            'Crédits Encaissés',
            'Total Décaissé',
            'Solde Caisse',
            'Montant Versé',
            'Dépôt Wave/MTN',
            'Dépôt Chèque',
            'Écart',
            'Statut',
        ];
    }

    /**
     * @param Caisse $caisse
     */
    public function map($caisse): array
    {
        return [
            $caisse->date_caisse->format('d/m/Y'),
            $caisse->emplacement?->nom ?? '—',
            $caisse->responsable?->name ?? '—',
            (float) $caisse->total_encaisse,
            (float) $caisse->total_credits_encaisses,
            (float) $caisse->total_decaisse,
            (float) $caisse->solde_caisse,
            (float) $caisse->montant_verse,
            (float) $caisse->depot_wave_mtn,
            (float) $caisse->depot_cheque,
            (float) $caisse->ecart,
            $caisse->statut?->getLabel() ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
