<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 未使用のカラムを削除します。
     * - name: usernameに統一
     * - sms_verification_code: Cacheに保存されているため不要
     * - email_verification_code: Cacheに保存されているため不要
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 未使用カラムを削除
            $table->dropColumn([
                'name',
                'sms_verification_code',
                'email_verification_code',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // カラムを復元
            $table->string('name')->after('user_id');
            $table->string('sms_verification_code', 6)->nullable()->after('residence');
            $table->string('email_verification_code', 6)->nullable()->after('sms_verified_at');
        });
    }
};

