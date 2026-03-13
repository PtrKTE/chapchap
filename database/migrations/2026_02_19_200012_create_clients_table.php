<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('nom', 150);
            $table->string('telephone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('adresse')->nullable();
            $table->string('quartier_zone', 100)->nullable();
            $table->string('type_client', 30); // TypeClient enum
            $table->string('secteur_activite', 100)->nullable();
            $table->string('contact_principal', 100)->nullable();
            $table->string('fonction_contact', 100)->nullable();
            $table->foreignId('commercial_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode_paiement_habituel', 20)->nullable(); // ModePaiement enum
            $table->string('conditions_paiement', 100)->nullable();
            $table->date('date_enregistrement');
            $table->boolean('actif')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
