<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('nom', 100);
            $table->string('categorie', 30); // CategorieProduit enum
            $table->string('unite_stock', 20); // kg, unite, paquet
            $table->string('unite_vente', 20); // kg, unite, paquet
            $table->decimal('prix_vente_defaut', 12, 2)->default(0);
            $table->decimal('seuil_alerte_stock', 10, 2)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
