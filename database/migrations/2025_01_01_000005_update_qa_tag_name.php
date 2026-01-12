<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 既存の「Q&A」タグを「Q&A（なんでも質問）」に更新
        DB::table('threads')
            ->where('tag', 'Q&A')
            ->update(['tag' => 'Q&A（なんでも質問）']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ロールバック時は「Q&A（なんでも質問）」を「Q&A」に戻す
        DB::table('threads')
            ->where('tag', 'Q&A（なんでも質問）')
            ->update(['tag' => 'Q&A']);
    }
};
