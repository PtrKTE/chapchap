<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30);
            $table->dateTime('date_mouvement');
            $table->foreignId('produit_id')->constrained('produits');
            $table->foreignId('emplacement_id')->constrained('emplacements');
            $table->string('type_mouvement', 30); // TypeMouvement enum
            $table->decimal('quantite', 10, 3); // Toujours positive
            $table->decimal('cout_unitaire', 12, 4)->default(0);
            $table->decimal('valeur', 14, 2)->default(0);
            $table->foreignId('lot_id')->nullable()->constrained('lots')->nullOnDelete();
            $table->foreignId('production_id')->nullable()->constrained('productions')->nullOnDelete();
            $table->foreignId('vente_id')->nullable()->constrained('ventes')->nullOnDelete();
            $table->foreignId('transfert_id')->nullable()->constrained('transferts')->nullOnDelete();
            $table->text('motif')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_stock');
    }
};
