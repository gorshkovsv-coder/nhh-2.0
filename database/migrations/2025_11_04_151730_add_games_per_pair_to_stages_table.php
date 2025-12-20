<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            if (!Schema::hasColumn('stages', 'games_per_pair')) {
                $table->unsignedTinyInteger('games_per_pair')->default(1)->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            if (Schema::hasColumn('stages', 'games_per_pair')) {
                $table->dropColumn('games_per_pair');
            }
        });
    }
};
