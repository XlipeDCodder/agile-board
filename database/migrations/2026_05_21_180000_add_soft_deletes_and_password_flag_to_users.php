<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Soft delete: "excluir" um usuário no admin marca deleted_at em vez
            // de apagar a row, preservando o histórico (cards criados, comentários,
            // apontamentos seguem com nome do autor). User com deleted_at != null
            // não consegue mais logar.
            $table->softDeletes();

            // Quando o admin cria/reseta um usuário com senha temporária, esse
            // flag fica true. O middleware ForcePasswordChange força o usuário
            // pra página de troca obrigatória logo depois do login, antes de
            // qualquer outra rota.
            $table->boolean('must_change_password')->default(false)->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('must_change_password');
        });
    }
};
