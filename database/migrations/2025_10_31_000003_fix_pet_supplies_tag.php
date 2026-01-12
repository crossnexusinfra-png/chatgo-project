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
        // 「ペット用品・フード」タグを「その他」に変更
        DB::table('threads')
            ->where('tag', 'ペット用品・フード')
            ->update(['tag' => 'その他']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ロールバック時は何もしない（元の値に戻すことはできない）
    }
};

