<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EmplacementSeeder::class,
            FournisseurSeeder::class,
            ProduitSeeder::class,
            CategorieChargeSeeder::class,
            GrilleRendementSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}
