<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            // Poids total du lot saisi manuellement à la réception (en kg)
            // Le poids moyen par poulet sera calculé : poids_total_lot / quantite_recue
            $table->decimal('poids_total_lot', 10, 3)->nullable()->after('quantite_recue');
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropColumn('poids_total_lot');
        });
    }
};
