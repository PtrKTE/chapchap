<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_emplacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained('produits');
            $table->foreignId('emplacement_id')->constrained('emplacements');
            $table->decimal('quantite', 10, 3)->default(0);
            $table->decimal('cout_moyen_pondere', 12, 4)->default(0);
            $table->decimal('valeur_stock', 14, 2)->default(0);
            $table->dateTime('derniere_entree')->nullable();
            $table->dateTime('derniere_sortie')->nullable();
            $table->timestamps();

            $table->unique(['produit_id', 'emplacement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_emplacements');
    }
};
