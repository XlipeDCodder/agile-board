<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * No Postgres, $table->enum('type', ['task','bug']) cria a coluna como
     * VARCHAR + um CHECK constraint chamado items_type_check que rejeita
     * qualquer valor fora da lista original. A migration anterior alterou
     * o tipo da coluna mas não removeu o CHECK, então inserts com
     * type='reabertura' continuavam falhando. Aqui dropamos o constraint
     * pra valer.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE items DROP CONSTRAINT IF EXISTS items_type_check');
        }
    }

    public function down(): void
    {
        // Sem rollback: recriar o constraint exigiria conhecer os valores
        // originais e poderia falhar se houver linhas com type='reabertura'.
    }
};
