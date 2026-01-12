<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * AdminMessageテーブルを多言語カラムからキーベースに変換します。
     * 既存のデータは、一時的なキーとして保存されます。
     */
    public function up(): void
    {
        // title_key, body_key, title, bodyカラムを追加
        Schema::table('admin_messages', function (Blueprint $table) {
            $table->string('title_key')->nullable()->after('id');
            $table->string('body_key')->nullable()->after('title_key');
            $table->string('title')->nullable()->after('body_key');
            $table->text('body')->nullable()->after('title');
        });
        
        // 既存データを移行
        // 既存のtitle_ja, body_jaを直接保存（後でキーに変換可能）
        $messages = DB::table('admin_messages')->get();
        foreach ($messages as $message) {
            // 既存のデータを直接保存（キーは後で設定可能）
            DB::table('admin_messages')
                ->where('id', $message->id)
                ->update([
                    'title' => $message->title_ja,
                    'body' => $message->body_ja,
                ]);
        }
        
        // 古いカラムを削除
        Schema::table('admin_messages', function (Blueprint $table) {
            $table->dropColumn(['title_ja', 'title_en', 'body_ja', 'body_en']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 多言語カラムを復元
        Schema::table('admin_messages', function (Blueprint $table) {
            $table->string('title_ja')->nullable()->after('id');
            $table->string('title_en')->nullable()->after('title_ja');
            $table->text('body_ja')->nullable()->after('title_en');
            $table->text('body_en')->nullable()->after('body_ja');
        });
        
        // 既存データを復元
        $messages = DB::table('admin_messages')->get();
        foreach ($messages as $message) {
            DB::table('admin_messages')
                ->where('id', $message->id)
                ->update([
                    'title_ja' => $message->title,
                    'body_ja' => $message->body,
                ]);
        }
        
        // 古いカラムを削除
        Schema::table('admin_messages', function (Blueprint $table) {
            $table->dropColumn(['title_key', 'body_key', 'title', 'body']);
        });
    }
};

