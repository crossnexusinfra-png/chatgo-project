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
        // 既存のデータでcompletedがfalseのものをnullに更新（未処理として扱う）
        DB::table('suggestions')
            ->where('completed', false)
            ->update(['completed' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ロールバック時はnullをfalseに戻す
        DB::table('suggestions')
            ->whereNull('completed')
            ->update(['completed' => false]);
    }
};
