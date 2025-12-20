<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tournaments', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('season')->nullable();
            $t->enum('format', ['round_robin','single_elim','groups_plus_playoff'])->default('round_robin');
            $t->json('settings')->nullable();
            $t->enum('status',['draft','active','archived'])->default('draft');
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('tournaments'); }
};
