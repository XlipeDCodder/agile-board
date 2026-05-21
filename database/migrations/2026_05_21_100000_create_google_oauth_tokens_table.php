<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_oauth_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Ambos os tokens são cifrados via cast 'encrypted' no model.
            // Guardamos como text porque tokens cifrados ficam maiores que o original.
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('scopes', 500)->nullable();
            // Email do Google retornado pelo userinfo no callback — usado pra
            // exibir "Conectado como X" e pra validar domínio na reconexão.
            $table->string('google_email', 320);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_oauth_tokens');
    }
};
