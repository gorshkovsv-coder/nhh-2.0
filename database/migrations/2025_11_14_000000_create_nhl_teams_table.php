<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nhl_teams', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();   // ANA, BOS, DET, WPG и т.п.
            $table->string('name');                // Полное название команды
            $table->string('logo_path')->nullable(); // Путь к файлу логотипа на диске
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nhl_teams');
    }
};
