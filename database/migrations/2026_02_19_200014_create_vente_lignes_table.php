<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vente_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_id')->constrained('ventes')->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained('produits');
            $table->decimal('quantite', 10, 3);
            $table->decimal('kilos', 10, 3)->nullable();
            $table->decimal('prix_unitaire', 12, 2);
            $table->decimal('montant_ligne', 14, 2)->default(0);
            $table->decimal('cout_revient', 12, 4)->default(0);
            $table->decimal('marge_ligne', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vente_lignes');
    }
};
