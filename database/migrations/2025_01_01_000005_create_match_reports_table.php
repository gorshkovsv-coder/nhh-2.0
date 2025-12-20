<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('match_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('match_id')->constrained()->cascadeOnDelete();
            $t->foreignId('reporter_participant_id')->constrained('tournament_participants')->cascadeOnDelete();
            $t->unsignedSmallInteger('score_home');
            $t->unsignedSmallInteger('score_away');
            $t->boolean('ot')->default(false);
            $t->boolean('so')->default(false);
            $t->enum('status',['pending','confirmed','rejected'])->default('pending');
            $t->foreignId('confirmer_participant_id')->nullable()->constrained('tournament_participants')->nullOnDelete();
            $t->text('comment')->nullable();
            $t->json('attachments')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('match_reports'); }
};
