<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            // 日本語と英語のカラムを追加（まずnullableとして追加）
            $table->string('title_ja')->nullable()->after('title');
            $table->string('title_en')->nullable()->after('title_ja');
            $table->text('body_ja')->nullable()->after('body');
            $table->text('body_en')->nullable()->after('body_ja');
        });

        // 既存のデータを日本語カラムにコピー
        DB::table('admin_messages')->update([
            'title_ja' => DB::raw('title'),
            'body_ja' => DB::raw('body'),
        ]);

        // body_jaはnullableのままにする（アプリケーションレベルでバリデーション）

        // 古いカラムを削除
        Schema::table('admin_messages', function (Blueprint $table) {
            $table->dropColumn(['title', 'body']);
        });
    }

    public function down(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            // 古いカラムを復元（まずnullableとして追加）
            $table->string('title')->nullable()->after('id');
            $table->text('body')->nullable()->after('title');
        });

        // 日本語のデータを古いカラムにコピー
        DB::table('admin_messages')->update([
            'title' => DB::raw('COALESCE(title_ja, \'\')'),
            'body' => DB::raw('COALESCE(body_ja, \'\')'),
        ]);

        // bodyをNOT NULLに変更（既にデータがコピーされているので安全）
        // ただし、body_jaがNULLの場合は空文字列にしているので、NOT NULLにできる
        Schema::table('admin_messages', function (Blueprint $table) {
            $table->text('body')->nullable(false)->change();
        });

        // 多言語カラムを削除
        Schema::table('admin_messages', function (Blueprint $table) {
            $table->dropColumn(['title_ja', 'title_en', 'body_ja', 'body_en']);
        });
    }
};

