<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('production_lignes', function (Blueprint $table) {
            // Quantité en nombre d'unités (ex: 100 poulets effilés)
            // Nullable car certains produits sont uniquement mesurés en kg
            $table->integer('quantite_unite')->nullable()->after('produit_id');
        });
    }

    public function down(): void
    {
        Schema::table('production_lignes', function (Blueprint $table) {
            $table->dropColumn('quantite_unite');
        });
    }
};
