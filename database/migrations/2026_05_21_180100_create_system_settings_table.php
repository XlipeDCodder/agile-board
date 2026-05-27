<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            // value como text pra acomodar bool / string / int serializados como string.
            // Casting acontece no model SystemSetting (get/set).
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed inicial: por default, o registro público fica DESABILITADO.
        // Sistema corporativo deve começar fechado — admin cadastra usuários.
        DB::table('system_settings')->insert([
            'key' => 'registration_enabled',
            'value' => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
