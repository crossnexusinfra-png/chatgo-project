<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // R18タグ（3種類）を定義
        $r18Tags = [
            '成人向けメディア・コンテンツ・創作',
            '性体験談・性的嗜好・フェティシズム',
            'アダルト業界・風俗・ナイトワーク'
        ];
        
        // 既存のR18タグのスレッドをis_r18=trueに更新
        \DB::table('threads')
            ->whereIn('tag', $r18Tags)
            ->update(['is_r18' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // すべてのスレッドのis_r18をfalseに戻す
        \DB::table('threads')
            ->update(['is_r18' => false]);
    }
};
