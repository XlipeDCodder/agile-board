<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Foto de perfil (caminho relativo no disk 'public', ex:
            // avatars/xxxx.jpg). Null = sem foto, frontend mostra iniciais.
            // Migration puramente aditiva — zero impacto nos dados existentes.
            $table->string('avatar_path', 255)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
