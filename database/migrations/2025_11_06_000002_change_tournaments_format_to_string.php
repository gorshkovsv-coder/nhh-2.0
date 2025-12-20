<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('tournaments', 'format')) return;

        // На всякий случай нормализуем странные значения
        DB::table('tournaments')
            ->whereNull('format')
            ->update(['format' => 'round_robin']);

        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('format', 32)->default('round_robin')->change();
        });
    }

    public function down(): void
    {
        // Возврат к ENUM (если очень нужно)
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `tournaments`
                MODIFY `format`
                ENUM('round_robin','groups_playoff') NOT NULL DEFAULT 'round_robin'");
        } else {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->string('format', 32)->default('round_robin')->change();
            });
        }
    }
};
