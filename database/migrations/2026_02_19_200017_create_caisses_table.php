<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caisses', function (Blueprint $table) {
            $table->id();
            $table->date('date_caisse');
            $table->foreignId('emplacement_id')->constrained('emplacements');
            $table->decimal('total_encaisse', 14, 2)->default(0);
            $table->decimal('total_credits_encaisses', 14, 2)->default(0);
            $table->decimal('total_decaisse', 14, 2)->default(0);
            $table->decimal('solde_caisse', 14, 2)->default(0);
            $table->decimal('montant_verse', 14, 2)->default(0);
            $table->decimal('depot_wave_mtn', 14, 2)->default(0);
            $table->decimal('depot_cheque', 14, 2)->default(0);
            $table->decimal('montant_especes', 14, 2)->default(0);
            $table->decimal('ecart', 14, 2)->default(0);
            $table->string('statut', 20)->default('ouverte'); // StatutCaisse enum
            $table->foreignId('responsable_id')->constrained('users');
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caisses');
    }
};
