<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventes', function (Blueprint $table) {
            $table->id();
            $table->string('numero_recu', 30)->unique();
            $table->dateTime('date_vente');
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('commercial_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('emplacement_id')->constrained('emplacements');
            $table->string('canal', 20); // Canal enum
            $table->decimal('montant_total', 14, 2)->default(0);
            $table->decimal('remise', 12, 2)->default(0);
            $table->decimal('montant_net', 14, 2)->default(0);
            $table->decimal('montant_recu', 14, 2)->default(0);
            $table->decimal('montant_restant', 14, 2)->default(0);
            $table->string('statut_paiement', 20)->default('credit'); // StatutPaiement enum
            $table->string('mode_paiement', 20)->nullable(); // ModePaiement enum
            $table->date('date_reglement_complet')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->boolean('annulee')->default(false);
            $table->dateTime('date_annulation')->nullable();
            $table->text('motif_annulation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventes');
    }
};
