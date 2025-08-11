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
        Schema::table('items', function (Blueprint $table) {
            // Adiciona a coluna para o relacionamento de subtarefa.
            // Ela é "nullable" porque tarefas-pai não têm um pai.
            // O "constrained" aponta para a própria tabela 'items'.
            // O "nullOnDelete" garante que se a tarefa-pai for deletada,
            // a subtarefa não será deletada, apenas perderá o vínculo.
            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('items')
                  ->nullOnDelete()
                  ->after('id'); // Coloca a coluna logo após o ID por organização
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Remove a chave estrangeira e a coluna se a migration for revertida
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};