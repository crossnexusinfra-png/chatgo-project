<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * フレンド申請条件の会話集計ルール変更により、旧データは無効のため削除する。
     */
    public function up(): void
    {
        DB::table('thread_interactions')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // データ復元不可
    }
};
