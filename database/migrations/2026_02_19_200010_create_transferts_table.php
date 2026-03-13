<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->dateTime('date_transfert');
            $table->foreignId('emplacement_source_id')->constrained('emplacements');
            $table->foreignId('emplacement_dest_id')->constrained('emplacements');
            $table->string('statut', 20)->default('en_cours'); // StatutTransfert enum
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('recu_par')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('date_reception')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferts');
    }
};
