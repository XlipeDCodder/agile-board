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
            // Esta coluna armazenará o timestamp de quando uma subtarefa foi concluída.
            // Será NULL se não estiver concluída.
            $table->timestamp('completed_at')->nullable()->after('order_in_column');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
