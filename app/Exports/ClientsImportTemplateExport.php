<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Génère un fichier Excel servant de modèle pour l'import de clients.
 *
 * Contient :
 * - La ligne d'en-têtes avec les colonnes attendues
 * - 2 exemples de lignes pour guider l'utilisateur
 * - Une mise en forme claire (en-tête en gras, fond coloré)
 */
class ClientsImportTemplateExport implements FromArray, ShouldAutoSize, WithStyles
{
    public function array(): array
    {
        return [
            // Ligne 1 : en-têtes (noms de colonnes attendus par ClientsImport)
            ['nom', 'telephone', 'email', 'quartier_zone', 'type_client', 'contact_principal', 'notes'],

            // Exemples pour guider la saisie
            ['SITA Abidjan', '0707000001', 'contact@sita.ci', 'Plateau', 'supermarche', 'Jean Koné', 'Client B2B'],
            ['Restaurant Le Maquis', '0505000002', '', 'Cocody', 'restaurant', 'Marie Coulibaly', ''],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // En-tête en gras avec fond orange CHAPCHAP
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'F97316']],
            ],
            // Lignes d'exemple en gris clair
            2 => ['fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'F3F4F6']]],
            3 => ['fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'F3F4F6']]],
        ];
    }
}
