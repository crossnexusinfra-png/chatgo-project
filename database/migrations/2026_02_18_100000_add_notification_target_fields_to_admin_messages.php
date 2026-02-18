<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            $table->boolean('is_welcome')->default(false)->after('coin_amount')->comment('初回登録時自動送信テンプレート');
            $table->boolean('target_is_adult')->nullable()->after('is_welcome')->comment('18歳以上のみ:1, 18歳未満のみ:0, 指定なし:null');
            $table->json('target_nationalities')->nullable()->after('target_is_adult')->comment('対象国籍コードの配列');
            $table->timestamp('target_registered_after')->nullable()->after('target_nationalities')->comment('この日時以降に登録したユーザー');
            $table->timestamp('target_registered_before')->nullable()->after('target_registered_after')->comment('この日時以前に登録したユーザー');
        });
    }

    public function down(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            $table->dropColumn([
                'is_welcome',
                'target_is_adult',
                'target_nationalities',
                'target_registered_after',
                'target_registered_before',
            ]);
        });
    }
};
