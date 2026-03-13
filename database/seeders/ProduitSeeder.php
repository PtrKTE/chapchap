<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Produit;
use Illuminate\Database\Seeder;

class ProduitSeeder extends Seeder
{
    public function run(): void
    {
        $produits = [
            ['code' => 'EFFIL', 'nom' => 'Poulet effilé', 'categorie' => 'volaille', 'unite_stock' => 'unite', 'unite_vente' => 'unite', 'prix_vente_defaut' => 2500],
            ['code' => 'PAC', 'nom' => 'Poulet PAC', 'categorie' => 'volaille', 'unite_stock' => 'kg', 'unite_vente' => 'kg', 'prix_vente_defaut' => 2150],
            ['code' => 'ESCAL', 'nom' => 'Escalopes', 'categorie' => 'decoupe', 'unite_stock' => 'kg', 'unite_vente' => 'kg', 'prix_vente_defaut' => 3800],
            ['code' => 'CUISS', 'nom' => 'Cuisses', 'categorie' => 'decoupe', 'unite_stock' => 'kg', 'unite_vente' => 'kg', 'prix_vente_defaut' => 2500],
            ['code' => 'AILES', 'nom' => 'Ailes', 'categorie' => 'decoupe', 'unite_stock' => 'kg', 'unite_vente' => 'kg', 'prix_vente_defaut' => 2000],
            ['code' => 'CARC', 'nom' => 'Carcasses', 'categorie' => 'decoupe', 'unite_stock' => 'kg', 'unite_vente' => 'kg', 'prix_vente_defaut' => 1000],
            ['code' => 'GESIER', 'nom' => 'Gésiers', 'categorie' => 'abat', 'unite_stock' => 'kg', 'unite_vente' => 'kg', 'prix_vente_defaut' => 2500],
            ['code' => 'FOIE', 'nom' => 'Foies', 'categorie' => 'abat', 'unite_stock' => 'kg', 'unite_vente' => 'kg', 'prix_vente_defaut' => 1000],
            ['code' => 'PATTE', 'nom' => 'Pattes', 'categorie' => 'abat', 'unite_stock' => 'kg', 'unite_vente' => 'kg', 'prix_vente_defaut' => 1500],
            ['code' => 'COUS', 'nom' => 'Cous', 'categorie' => 'abat', 'unite_stock' => 'kg', 'unite_vente' => 'kg', 'prix_vente_defaut' => 800],
            ['code' => 'BROCH', 'nom' => 'Brochettes', 'categorie' => 'decoupe', 'unite_stock' => 'kg', 'unite_vente' => 'kg', 'prix_vente_defaut' => 1000],
            ['code' => 'INTEST', 'nom' => 'Intestins', 'categorie' => 'abat', 'unite_stock' => 'kg', 'unite_vente' => 'kg', 'prix_vente_defaut' => 500],
            ['code' => 'DECOUP', 'nom' => 'Découpes (assortiment)', 'categorie' => 'decoupe', 'unite_stock' => 'kg', 'unite_vente' => 'kg', 'prix_vente_defaut' => 2000],
            ['code' => 'COQUE', 'nom' => 'Coquelets', 'categorie' => 'volaille', 'unite_stock' => 'kg', 'unite_vente' => 'kg', 'prix_vente_defaut' => 3550],
            ['code' => 'OEUF', 'nom' => 'Œufs', 'categorie' => 'oeuf', 'unite_stock' => 'unite', 'unite_vente' => 'unite', 'prix_vente_defaut' => 2200],
            ['code' => 'PEAU', 'nom' => 'Peaux', 'categorie' => 'abat', 'unite_stock' => 'kg', 'unite_vente' => 'kg', 'prix_vente_defaut' => 500],
            ['code' => 'PM', 'nom' => 'Poulets Morts', 'categorie' => 'volaille', 'unite_stock' => 'unite', 'unite_vente' => 'unite', 'prix_vente_defaut' => 1300],
        ];

        foreach ($produits as $produit) {
            Produit::firstOrCreate(
                ['code' => $produit['code']],
                $produit
            );
        }
    }
}
