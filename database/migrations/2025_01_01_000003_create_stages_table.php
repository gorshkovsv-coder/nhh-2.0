<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->enum('type',['group','playoff'])->default('group');
            $t->json('settings')->nullable();
            $t->unsignedInteger('order')->default(1);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('stages'); }
};
