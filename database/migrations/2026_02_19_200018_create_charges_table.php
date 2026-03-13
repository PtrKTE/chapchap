<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->date('date_charge');
            $table->foreignId('categorie_id')->constrained('categories_charges');
            $table->string('libelle', 255);
            $table->decimal('montant', 14, 2);
            $table->string('mode_paiement', 20); // ModePaiement enum
            $table->foreignId('fournisseur_id')->nullable()->constrained('fournisseurs')->nullOnDelete();
            $table->foreignId('emplacement_id')->constrained('emplacements');
            $table->string('personne', 100)->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
