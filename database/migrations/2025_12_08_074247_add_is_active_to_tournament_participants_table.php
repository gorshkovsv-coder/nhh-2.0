<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_participants', function (Blueprint $table) {
            $table->boolean('is_active')
                ->default(true)
                ->after('user_id'); // или после любого удобного поля
        });
    }

    public function down(): void
    {
        Schema::table('tournament_participants', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
