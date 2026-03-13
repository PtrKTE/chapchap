<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventaires', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->date('date_inventaire');
            $table->foreignId('emplacement_id')->constrained('emplacements');
            $table->string('statut', 20)->default('en_cours'); // en_cours, termine, valide
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventaires');
    }
};
