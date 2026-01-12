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
        // languageカラムを追加（デフォルト値は'EN'）
        Schema::table('users', function (Blueprint $table) {
            $table->string('language', 2)->default('EN')->after('bio');
        });

        // 既存のsettingsからlanguageを移行
        $users = DB::table('users')->get();
        
        foreach ($users as $user) {
            $language = 'EN'; // デフォルト値
            
            if ($user->settings !== null && $user->settings !== '') {
                $settings = json_decode($user->settings, true);
                if ($settings !== null && isset($settings['language'])) {
                    $language = $settings['language'];
                    // 小文字の場合は大文字に変換
                    if ($language === 'ja') $language = 'JA';
                    if ($language === 'en') $language = 'EN';
                }
            }
            
            DB::table('users')
                ->where('user_id', $user->user_id)
                ->update(['language' => $language]);
        }

        // languageカラムをNOT NULLに変更
        Schema::table('users', function (Blueprint $table) {
            $table->string('language', 2)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ロールバック時はlanguageカラムを削除
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};

