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
        // 既存のスレッドのaccess_countを0に初期化
        DB::table('threads')->update(['access_count' => 0]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // このマイグレーションは元に戻せない（データの初期化のため）
    }
};
