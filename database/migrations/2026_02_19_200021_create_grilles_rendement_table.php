<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grilles_rendement', function (Blueprint $table) {
            $table->id();
            $table->decimal('poids_poulet_kg', 4, 2);
            $table->foreignId('produit_id')->constrained('produits');
            $table->decimal('poids_produit_kg', 6, 4);
            $table->decimal('prix_vente_kg', 12, 2);
            $table->decimal('valorisation', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grilles_rendement');
    }
};
