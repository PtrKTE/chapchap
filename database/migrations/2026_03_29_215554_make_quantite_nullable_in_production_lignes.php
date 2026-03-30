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
        Schema::table('production_lignes', function (Blueprint $table) {
            // quantite (kg) devient nullable car on peut saisir uniquement en unités
            $table->decimal('quantite', 10, 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('production_lignes', function (Blueprint $table) {
            $table->decimal('quantite', 10, 3)->nullable(false)->default(0)->change();
        });
    }
};
