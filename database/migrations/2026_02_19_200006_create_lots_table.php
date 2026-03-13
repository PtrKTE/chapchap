<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->date('date_reception');
            $table->foreignId('fournisseur_id')->constrained('fournisseurs');
            $table->string('type_produit', 20); // poulet, oeuf
            $table->integer('quantite_recue');
            $table->integer('quantite_morts')->default(0);
            $table->integer('quantite_refuses')->default(0);
            $table->integer('quantite_utilisable')->default(0);
            $table->decimal('prix_unitaire', 12, 2);
            $table->decimal('cout_transport', 12, 2)->default(0);
            $table->decimal('autres_couts', 12, 2)->default(0);
            $table->decimal('cout_total', 14, 2)->default(0);
            $table->decimal('cout_moyen_unitaire', 12, 4)->default(0);
            $table->decimal('montant_facture', 14, 2)->default(0);
            $table->string('statut_facture', 20)->default('non_reglee'); // StatutFacture enum
            $table->decimal('montant_regle', 14, 2)->default(0);
            $table->date('date_reglement')->nullable();
            $table->string('mode_reglement', 50)->nullable();
            $table->integer('oeufs_casses')->default(0);
            $table->date('date_production')->nullable();
            $table->text('observations')->nullable();
            $table->string('piece_jointe', 255)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
