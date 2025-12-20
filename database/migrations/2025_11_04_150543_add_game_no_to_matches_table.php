<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            if (!Schema::hasColumn('matches', 'game_no')) {
                $table->unsignedTinyInteger('game_no')->default(1)->after('away_participant_id');
            }
            // создаём уникальный индекс на пару + номер игры
            // имя индекса можно оставить как ниже
            $table->unique(
                ['stage_id','home_participant_id','away_participant_id','game_no'],
                'matches_stage_pair_game_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // удаляем уникальный индекс (если имя другое — поправь здесь)
            $table->dropUnique('matches_stage_pair_game_unique');

            if (Schema::hasColumn('matches', 'game_no')) {
                $table->dropColumn('game_no');
            }
        });
    }
};
