<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Fournisseur;
use Illuminate\Database\Seeder;

class FournisseurSeeder extends Seeder
{
    public function run(): void
    {
        Fournisseur::firstOrCreate(
            ['nom' => 'OVECH RANCH'],
            [
                'type' => 'volaille',
                'adresse' => 'Côte d\'Ivoire',
                'actif' => true,
            ]
        );
    }
}
