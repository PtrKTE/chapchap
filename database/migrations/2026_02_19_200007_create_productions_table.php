<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->date('date_production');
            $table->foreignId('lot_id')->constrained('lots');
            $table->integer('nb_poulets_traites');
            $table->decimal('poids_moyen_entrant', 6, 3)->nullable();
            $table->decimal('poids_total_entrant', 10, 3)->nullable();
            $table->decimal('pertes_casse', 8, 3)->default(0);
            $table->decimal('rendement', 6, 4)->nullable();
            $table->string('statut', 20)->default('en_cours'); // StatutProduction enum
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('date_validation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productions');
    }
};
