<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CategorieCharge;
use Illuminate\Database\Seeder;

class CategorieChargeSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['nom' => 'Carburant', 'type' => 'variable', 'montant_reference' => 200000],
            ['nom' => 'Électricité', 'type' => 'fixe', 'montant_reference' => 400000],
            ['nom' => 'Loyer', 'type' => 'fixe', 'montant_reference' => 180000],
            ['nom' => 'Main d\'œuvre abattage', 'type' => 'variable', 'montant_reference' => null],
            ['nom' => 'Nourriture personnel', 'type' => 'variable', 'montant_reference' => null],
            ['nom' => 'Gaz abattage', 'type' => 'variable', 'montant_reference' => 30000],
            ['nom' => 'Gaz cuisine', 'type' => 'variable', 'montant_reference' => 2400],
            ['nom' => 'Emballages/Sachets', 'type' => 'variable', 'montant_reference' => 54000],
            ['nom' => 'Transport/Péage', 'type' => 'variable', 'montant_reference' => null],
            ['nom' => 'Entretien/Réparations', 'type' => 'variable', 'montant_reference' => null],
            ['nom' => 'Véhicule', 'type' => 'variable', 'montant_reference' => null],
            ['nom' => 'Fournitures bureau', 'type' => 'variable', 'montant_reference' => 5500],
            ['nom' => 'Sécurité', 'type' => 'fixe', 'montant_reference' => null],
            ['nom' => 'Salaires/Compléments', 'type' => 'fixe', 'montant_reference' => null],
            ['nom' => 'Commissions commerciaux', 'type' => 'variable', 'montant_reference' => null],
            ['nom' => 'Pharmacie', 'type' => 'variable', 'montant_reference' => 15000],
            ['nom' => 'Eau potable', 'type' => 'variable', 'montant_reference' => null],
            ['nom' => 'Divers', 'type' => 'variable', 'montant_reference' => null],
        ];

        foreach ($categories as $categorie) {
            CategorieCharge::firstOrCreate(
                ['nom' => $categorie['nom']],
                $categorie
            );
        }
    }
}
