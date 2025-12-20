<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_participants', function (Blueprint $table) {
            $table->foreignId('nhl_team_id')
                ->nullable()
                ->after('seed')
                ->constrained('nhl_teams')
                ->nullOnDelete();

            // В одном турнире одна команда не может быть выдана двум участникам
            $table->unique(['tournament_id', 'nhl_team_id'], 'tp_tournament_team_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_participants', function (Blueprint $table) {
            $table->dropUnique('tp_tournament_team_unique');
            $table->dropConstrainedForeignId('nhl_team_id');
        });
    }
};
