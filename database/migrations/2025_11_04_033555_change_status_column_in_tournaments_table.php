<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Меняем тип поля status на VARCHAR(32) с дефолтом "draft"
        DB::statement("ALTER TABLE tournaments MODIFY status VARCHAR(32) NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        // Если нужно откатить, верните сюда свой старый ENUM.
        // Пример (если раньше было только 'active'):
        // DB::statement(\"ALTER TABLE tournaments MODIFY status ENUM('active') NOT NULL DEFAULT 'active'\");
    }
};
