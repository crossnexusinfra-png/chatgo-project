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
        // threadsテーブルにインデックスを追加
        Schema::table('threads', function (Blueprint $table) {
            // tagカラムにインデックス（タグ検索で使用）
            $table->index('tag', 'threads_tag_index');
            // user_nameカラムにインデックス（ユーザーのスレッド検索で使用）
            $table->index('user_name', 'threads_user_name_index');
            // created_atカラムにインデックス（最新順ソートで使用）
            $table->index('created_at', 'threads_created_at_index');
            // access_countカラムにインデックス（人気順ソートで使用）
            $table->index('access_count', 'threads_access_count_index');
            // tagとcreated_atの複合インデックス（タグ検索＋最新順）
            $table->index(['tag', 'created_at'], 'threads_tag_created_at_index');
        });

        // thread_accessesテーブルにインデックスを追加
        Schema::table('thread_accesses', function (Blueprint $table) {
            // user_idカラムにインデックス（ユーザーのアクセス履歴検索で使用）
            // 外部キー制約がある場合、既にインデックスが作成されている可能性があるが、明示的に追加
            try {
                $table->index('user_id', 'thread_accesses_user_id_index');
            } catch (\Exception $e) {
                // 既に存在する場合は無視
            }
            // accessed_atカラムにインデックス（期間フィルタリングで使用）
            $table->index('accessed_at', 'thread_accesses_accessed_at_index');
            // user_idとthread_idの複合インデックス（ユーザー別のスレッドアクセス検索）
            $table->index(['user_id', 'thread_id'], 'thread_accesses_user_thread_index');
            // user_idとaccessed_atの複合インデックス（ユーザーの最近アクセス検索）
            $table->index(['user_id', 'accessed_at'], 'thread_accesses_user_accessed_index');
            // thread_idとaccessed_atの複合インデックス（スレッド別の期間集計）
            $table->index(['thread_id', 'accessed_at'], 'thread_accesses_thread_accessed_index');
        });

        // thread_favoritesテーブルにインデックスを追加
        Schema::table('thread_favorites', function (Blueprint $table) {
            // user_idカラムにインデックス（ユーザーのお気に入り検索で使用）
            // 外部キー制約がある場合、既にインデックスが作成されている可能性があるが、明示的に追加
            try {
                $table->index('user_id', 'thread_favorites_user_id_index');
            } catch (\Exception $e) {
                // 既に存在する場合は無視
            }
            // created_atカラムにインデックス（お気に入り追加順ソートで使用）
            $table->index('created_at', 'thread_favorites_created_at_index');
            // user_idとcreated_atの複合インデックス（ユーザーのお気に入りを新しい順で取得）
            $table->index(['user_id', 'created_at'], 'thread_favorites_user_created_index');
        });

        // responsesテーブルにインデックスを追加
        Schema::table('responses', function (Blueprint $table) {
            // thread_idカラムにインデックス（スレッド別のレスポンス取得で使用）
            // 外部キー制約がある場合、既にインデックスが作成されている可能性があるが、明示的に追加
            try {
                $table->index('thread_id', 'responses_thread_id_index');
            } catch (\Exception $e) {
                // 既に存在する場合は無視
            }
            // created_atカラムにインデックス（最新順ソートで使用）
            $table->index('created_at', 'responses_created_at_index');
        });

        // reportsテーブルにインデックスを追加
        Schema::table('reports', function (Blueprint $table) {
            // thread_idカラムにインデックス（スレッド別の通報取得で使用）
            $table->index('thread_id', 'reports_thread_id_index');
            // response_idカラムにインデックス（レスポンス別の通報取得で使用）
            $table->index('response_id', 'reports_response_id_index');
            // created_atカラムにインデックス（期間フィルタリングで使用）
            $table->index('created_at', 'reports_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropIndex('threads_tag_index');
            $table->dropIndex('threads_user_name_index');
            $table->dropIndex('threads_created_at_index');
            $table->dropIndex('threads_access_count_index');
            $table->dropIndex('threads_tag_created_at_index');
        });

        Schema::table('thread_accesses', function (Blueprint $table) {
            $table->dropIndex('thread_accesses_user_id_index');
            $table->dropIndex('thread_accesses_accessed_at_index');
            $table->dropIndex('thread_accesses_user_thread_index');
            $table->dropIndex('thread_accesses_user_accessed_index');
            $table->dropIndex('thread_accesses_thread_accessed_index');
        });

        Schema::table('thread_favorites', function (Blueprint $table) {
            $table->dropIndex('thread_favorites_user_id_index');
            $table->dropIndex('thread_favorites_created_at_index');
            $table->dropIndex('thread_favorites_user_created_index');
        });

        Schema::table('responses', function (Blueprint $table) {
            $table->dropIndex('responses_thread_id_index');
            $table->dropIndex('responses_created_at_index');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('reports_thread_id_index');
            $table->dropIndex('reports_response_id_index');
            $table->dropIndex('reports_created_at_index');
        });
    }

};

