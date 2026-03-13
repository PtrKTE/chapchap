<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfert_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfert_id')->constrained('transferts')->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained('produits');
            $table->decimal('quantite_envoyee', 10, 3);
            $table->decimal('quantite_recue', 10, 3)->nullable();
            $table->decimal('ecart', 10, 3)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfert_lignes');
    }
};
