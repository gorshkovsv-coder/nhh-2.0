<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('users')
            ->whereNull('avatar_path')
            ->orWhere('avatar_path', '')
            ->update(['avatar_path' => 'avatars/default.png']);
    }

    public function down(): void
    {
        // Откат: "забываем" дефолт, но только у тех, у кого именно он был
        DB::table('users')
            ->where('avatar_path', 'avatars/default.png')
            ->update(['avatar_path' => null]);
    }
};
