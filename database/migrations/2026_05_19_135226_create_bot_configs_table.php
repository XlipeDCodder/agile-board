<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_configs', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('gemini');
            $table->string('model')->default('gemini-2.0-flash');
            $table->text('api_key');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_configs');
    }
};
