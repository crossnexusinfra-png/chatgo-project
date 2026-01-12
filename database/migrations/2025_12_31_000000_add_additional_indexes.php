<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 追加のインデックスを追加してパフォーマンスを向上させます。
     */
    public function up(): void
    {
        // threadsテーブルに追加のインデックス
        Schema::table('threads', function (Blueprint $table) {
            // is_r18カラムにインデックス（R18フィルタリングで使用）
            $table->index('is_r18', 'threads_is_r18_index');
            // tagとis_r18の複合インデックス（タグ検索＋R18フィルタリング）
            $table->index(['tag', 'is_r18'], 'threads_tag_is_r18_index');
        });

        // responsesテーブルに追加のインデックス
        Schema::table('responses', function (Blueprint $table) {
            // user_nameカラムにインデックス（ユーザー検索で使用）
            $table->index('user_name', 'responses_user_name_index');
            // parent_response_idカラムにインデックス（返信機能で使用）
            // 外部キー制約がある場合、既にインデックスが作成されている可能性があるが、明示的に追加
            try {
                $table->index('parent_response_id', 'responses_parent_response_id_index');
            } catch (\Exception $e) {
                // 既に存在する場合は無視
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropIndex('threads_is_r18_index');
            $table->dropIndex('threads_tag_is_r18_index');
        });

        Schema::table('responses', function (Blueprint $table) {
            $table->dropIndex('responses_user_name_index');
            try {
                $table->dropIndex('responses_parent_response_id_index');
            } catch (\Exception $e) {
                // 存在しない場合は無視
            }
        });
    }
};

