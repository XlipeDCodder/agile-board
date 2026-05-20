<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Converte o enum de "type" em string(50) para suportar 'reabertura'
        // sem precisar de doctrine/dbal. Funciona em MySQL/MariaDB. Em SQLite
        // os enums são strings de qualquer forma — esse ALTER vira no-op.
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE items MODIFY type VARCHAR(50) NOT NULL DEFAULT 'task'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE items ALTER COLUMN type TYPE VARCHAR(50)');
            DB::statement("ALTER TABLE items ALTER COLUMN type SET DEFAULT 'task'");
        }
        // SQLite: enum já é tratado como string, nada a fazer.

        Schema::table('items', function (Blueprint $table) {
            // Reabertura
            $table->foreignId('reopened_from_id')->nullable()->after('parent_id')
                ->constrained('items')->nullOnDelete();
            $table->text('justification')->nullable()->after('description');

            // Previsão de término (ETA absoluto, distinto da estimation/poker)
            $table->integer('predicted_value')->nullable()->after('estimation');
            $table->string('predicted_unit', 10)->nullable()->after('predicted_value');

            // Impedimento — estado atual denormalizado para queries rápidas
            $table->boolean('is_blocked')->default(false)->after('status');
            $table->text('blocked_reason')->nullable()->after('is_blocked');
            $table->foreignId('blocked_by_item_id')->nullable()->after('blocked_reason')
                ->constrained('items')->nullOnDelete();
            $table->timestamp('blocked_at')->nullable()->after('blocked_by_item_id');
        });

        Schema::create('item_block_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('event', 20); // 'blocked' | 'unblocked'
            $table->text('reason')->nullable();
            $table->foreignId('blocked_by_item_id')->nullable()
                ->constrained('items')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_block_events');

        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopened_from_id');
            $table->dropColumn('justification');
            $table->dropColumn(['predicted_value', 'predicted_unit']);
            $table->dropColumn(['is_blocked', 'blocked_reason']);
            $table->dropConstrainedForeignId('blocked_by_item_id');
            $table->dropColumn('blocked_at');
        });

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE items MODIFY type ENUM('task','bug') NOT NULL DEFAULT 'task'");
        }
    }
};
