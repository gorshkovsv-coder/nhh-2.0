<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('standings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $t->foreignId('participant_id')->constrained('tournament_participants')->cascadeOnDelete();
            $t->unsignedSmallInteger('gp')->default(0);
            $t->unsignedSmallInteger('w')->default(0);
            $t->unsignedSmallInteger('otw')->default(0);
            $t->unsignedSmallInteger('otl')->default(0);
            $t->unsignedSmallInteger('l')->default(0);
            $t->unsignedSmallInteger('gf')->default(0);
            $t->unsignedSmallInteger('ga')->default(0);
            $t->integer('gd')->default(0);
            $t->unsignedSmallInteger('points')->default(0);
            $t->unsignedSmallInteger('tech_losses')->default(0);
            $t->timestamps();
            $t->unique(['stage_id','participant_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('standings'); }
};
