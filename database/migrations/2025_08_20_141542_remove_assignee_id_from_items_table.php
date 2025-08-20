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
            // Remove a chave estrangeira antes de remover a coluna
            $table->dropForeign(['assignee_id']);
            $table->dropColumn('assignee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Código para reverter a migration, caso seja necessário
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }
};
