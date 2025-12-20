<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tournament_participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('display_name');
            $t->unsignedInteger('seed')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->unique(['tournament_id','user_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('tournament_participants'); }
};
