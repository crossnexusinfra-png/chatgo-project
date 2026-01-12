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
        // 存在しないタグを「その他」に変更
        $invalidTags = [
            '車選び・購入',
            'バイク',
            '整備・メンテナンス',
            '運転・交通ルール',
            'カスタマイズ',
            'ペット用品・フード',
        ];
        
        foreach ($invalidTags as $invalidTag) {
            DB::table('threads')
                ->where('tag', $invalidTag)
                ->update(['tag' => 'その他']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ロールバック時は何もしない（元の値に戻すことはできない）
    }
};

