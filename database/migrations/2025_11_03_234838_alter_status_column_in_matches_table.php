<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Делаем статус строкой: scheduled / reported / confirmed / disputed
        DB::statement("ALTER TABLE matches MODIFY status VARCHAR(20) NOT NULL DEFAULT 'scheduled'");
    }

    public function down(): void
    {
        // Если нужно откатить: вернём как было (предположим tinyint(1))
        DB::statement("ALTER TABLE matches MODIFY status TINYINT(1) NOT NULL DEFAULT 0");
    }
};
