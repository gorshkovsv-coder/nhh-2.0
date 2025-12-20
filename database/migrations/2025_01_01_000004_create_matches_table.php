<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('matches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $t->foreignId('home_participant_id')->constrained('tournament_participants')->cascadeOnDelete();
            $t->foreignId('away_participant_id')->constrained('tournament_participants')->cascadeOnDelete();
            $t->dateTime('scheduled_at')->nullable();
            $t->enum('status',['pending','confirmed','disputed','void'])->default('pending');
            $t->unsignedSmallInteger('score_home')->nullable();
            $t->unsignedSmallInteger('score_away')->nullable();
            $t->boolean('ot')->default(false);
            $t->boolean('so')->default(false);
            $t->foreignId('reporter_id')->nullable()->constrained('tournament_participants')->nullOnDelete();
            $t->timestamp('confirmed_at')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->index(['stage_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('matches'); }
};
