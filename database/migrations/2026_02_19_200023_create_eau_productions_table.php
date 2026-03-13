<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eau_productions', function (Blueprint $table) {
            $table->id();
            $table->date('date_production');
            $table->integer('nb_paquets_produits');
            $table->integer('consommation_sachets')->nullable();
            $table->decimal('consommation_energie', 10, 2)->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eau_productions');
    }
};
