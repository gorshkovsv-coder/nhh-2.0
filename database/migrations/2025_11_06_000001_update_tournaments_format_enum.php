<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('tournaments', 'format')) {
            return;
        }

        // Нормализуем старые/левые значения перед ALTER
        DB::table('tournaments')
            ->whereNotIn('format', ['round_robin', 'groups_playoff'])
            ->update(['format' => 'round_robin']);

        // Для MySQL — расширяем ENUM. Для других СУБД можно заменить на string.
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `tournaments`
                MODIFY `format`
                ENUM('round_robin','groups_playoff') NOT NULL DEFAULT 'round_robin'");
        } else {
            // Фолбэк: просто string
            Schema::table('tournaments', function ($table) {
                $table->string('format', 32)->default('round_robin')->change();
            });
        }
    }

    public function down(): void
    {
        // Возврат к старому enum (если он был), чтобы миграция была обратимой.
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `tournaments`
                MODIFY `format`
                ENUM('round_robin','olympic') NOT NULL DEFAULT 'round_robin'");
        } else {
            Schema::table('tournaments', function ($table) {
                $table->string('format', 32)->default('round_robin')->change();
            });
        }
    }
};
