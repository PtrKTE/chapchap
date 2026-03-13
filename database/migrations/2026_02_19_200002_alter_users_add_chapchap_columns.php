<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telephone', 20)->nullable()->after('email');
            $table->string('profil', 30)->nullable()->after('telephone'); // Profil enum
            $table->foreignId('emplacement_id')->nullable()->after('profil')->constrained('emplacements')->nullOnDelete();
            $table->boolean('actif')->default(true)->after('emplacement_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('emplacement_id');
            $table->dropColumn(['telephone', 'profil', 'actif']);
        });
    }
};
