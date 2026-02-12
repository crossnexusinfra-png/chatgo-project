<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 送信時の表示言語を保存。送信者削除後も元言語を正しく判定するため。
     */
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->char('source_lang', 2)->nullable()->after('title')->comment('送信時の表示言語 JA/EN');
        });

        Schema::table('responses', function (Blueprint $table) {
            $table->char('source_lang', 2)->nullable()->after('body')->comment('送信時の表示言語 JA/EN');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropColumn('source_lang');
        });
        Schema::table('responses', function (Blueprint $table) {
            $table->dropColumn('source_lang');
        });
    }
};
