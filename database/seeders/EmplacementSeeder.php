<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Emplacement;
use Illuminate\Database\Seeder;

class EmplacementSeeder extends Seeder
{
    public function run(): void
    {
        $emplacements = [
            [
                'nom' => 'Site principal',
                'type' => 'site',
                'adresse' => 'Abidjan - Site abattoir + chambre froide',
                'actif' => true,
            ],
            [
                'nom' => 'Belleville',
                'type' => 'point_de_vente',
                'adresse' => 'Abidjan - Belleville',
                'actif' => true,
            ],
        ];

        foreach ($emplacements as $emplacement) {
            Emplacement::firstOrCreate(
                ['nom' => $emplacement['nom']],
                $emplacement
            );
        }
    }
}
