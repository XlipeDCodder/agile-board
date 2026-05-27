<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            // Dev/responsável que iniciou o deploy.
            $table->foreignId('deployer_id')->constrained('users');
            // 'staging' (homologação) ou 'production'.
            $table->string('environment', 20);
            // Estado do deploy:
            //   pending    — staging aguardando aprovação do admin
            //   approved   — staging aprovado (admin liberou pra ir pra prod)
            //   rejected   — staging rejeitado (com motivo)
            //   completed  — production registrado (após aprovação OU urgente)
            $table->string('status', 20);
            // Release notes / descrição do que foi deployado.
            $table->text('notes')->nullable();
            // Admin que aprovou/rejeitou (nullable — só staging tem).
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            // Liga o production ao staging que o originou (pra audit trail).
            // Production urgente (que pulou homologação) deixa null.
            $table->foreignId('linked_deployment_id')->nullable()->constrained('deployments')->nullOnDelete();
            // Flag: foi deploy direto em produção pulando homologação?
            // Útil pra relatórios / dashboards de "% deploys urgentes".
            $table->boolean('is_urgent')->default(false);
            $table->timestamps();

            $table->index(['environment', 'status']);
            $table->index(['item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
