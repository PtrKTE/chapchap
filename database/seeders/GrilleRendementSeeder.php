<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GrilleRendement;
use App\Models\Produit;
use Illuminate\Database\Seeder;

class GrilleRendementSeeder extends Seeder
{
    public function run(): void
    {
        // Grilles de rendement par poids de poulet
        // Clés string obligatoires : en PHP, les clés float (1.80, 2.00, 2.30)
        // sont tronquées en entiers (1, 2, 2) → collision et perte de données !
        $grilles = [
            // Poulet 1.8 kg — Valorisation totale : 4149.4 FCFA
            '1.80' => [
                ['produit' => 'ESCAL', 'poids' => 0.508, 'prix' => 3800],
                ['produit' => 'CUISS', 'poids' => 0.466, 'prix' => 2500],
                ['produit' => 'AILES', 'poids' => 0.170, 'prix' => 2000],
                ['produit' => 'PATTE', 'poids' => 0.068, 'prix' => 1000],
                ['produit' => 'CARC', 'poids' => 0.514, 'prix' => 1000],
                ['produit' => 'GESIER', 'poids' => 0.034, 'prix' => 2000],
                ['produit' => 'FOIE', 'poids' => 0.064, 'prix' => 1000],
            ],
            // Poulet 2.0 kg — Valorisation totale : 4310.8 FCFA
            '2.00' => [
                ['produit' => 'ESCAL', 'poids' => 0.526, 'prix' => 3800],
                ['produit' => 'CUISS', 'poids' => 0.452, 'prix' => 2500],
                ['produit' => 'AILES', 'poids' => 0.172, 'prix' => 2000],
                ['produit' => 'PATTE', 'poids' => 0.070, 'prix' => 1000],
                ['produit' => 'CARC', 'poids' => 0.612, 'prix' => 1000],
                ['produit' => 'GESIER', 'poids' => 0.030, 'prix' => 3000],
                ['produit' => 'FOIE', 'poids' => 0.066, 'prix' => 1000],
            ],
            // Poulet 2.3 kg — Valorisation totale : 5075 FCFA
            '2.30' => [
                ['produit' => 'ESCAL', 'poids' => 0.610, 'prix' => 3800],
                ['produit' => 'CUISS', 'poids' => 0.586, 'prix' => 2500],
                ['produit' => 'AILES', 'poids' => 0.194, 'prix' => 2000],
                ['produit' => 'PATTE', 'poids' => 0.094, 'prix' => 1000],
                ['produit' => 'CARC', 'poids' => 0.656, 'prix' => 1000],
                ['produit' => 'GESIER', 'poids' => 0.026, 'prix' => 3000],
                ['produit' => 'FOIE', 'poids' => 0.076, 'prix' => 1000],
            ],
        ];

        foreach ($grilles as $poidsPoulet => $lignes) {
            foreach ($lignes as $ligne) {
                $produit = Produit::where('code', $ligne['produit'])->first();

                if ($produit) {
                    GrilleRendement::firstOrCreate(
                        [
                            'poids_poulet_kg' => $poidsPoulet,
                            'produit_id' => $produit->id,
                        ],
                        [
                            'poids_produit_kg' => $ligne['poids'],
                            'prix_vente_kg' => $ligne['prix'],
                            'valorisation' => round($ligne['poids'] * $ligne['prix'], 2),
                        ]
                    );
                }
            }
        }
    }
}
