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
        Schema::create('items', function (Blueprint $table) {
            $table->id();

            // Relacionamentos
            $table->foreignId('column_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();

            // Dados do Item
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['task', 'bug'])->default('task');
            $table->enum('priority', ['Baixa', 'Média', 'Alta', 'Crítica'])->default('Média');
            $table->string('status')->default('Aberto');
            $table->float('estimation')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('order_in_column')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
