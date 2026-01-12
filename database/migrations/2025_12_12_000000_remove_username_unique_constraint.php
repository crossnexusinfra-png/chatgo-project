<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PostgreSQLでは制約名を明示的に指定する必要がある
        if (DB::getDriverName() === 'pgsql') {
            // PostgreSQLの場合、制約名を直接削除
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_username_unique');
        } else {
            // その他のデータベースの場合
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['username']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // unique制約を復元
            $table->unique('username');
        });
    }
};

