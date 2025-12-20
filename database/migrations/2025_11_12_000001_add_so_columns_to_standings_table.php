<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('standings', function (Blueprint $table) {
            $table->unsignedSmallInteger('sow')->default(0)->after('otw'); // Выиграл буллиты
            $table->unsignedSmallInteger('sol')->default(0)->after('otl'); // Проиграл буллиты
        });
    }

    public function down(): void
    {
        Schema::table('standings', function (Blueprint $table) {
            $table->dropColumn(['sow', 'sol']);
        });
    }
};
