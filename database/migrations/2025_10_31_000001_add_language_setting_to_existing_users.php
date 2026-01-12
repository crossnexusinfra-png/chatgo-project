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
        // 既存ユーザーに言語設定を追加
        // 国籍がJPの場合は'ja'、それ以外は'en'を設定
        $users = DB::table('users')->get();
        
        foreach ($users as $user) {
            $settings = json_decode($user->settings ?? '{}', true);
            
            // 既に言語設定がある場合はスキップ
            if (isset($settings['language'])) {
                continue;
            }
            
            // 国籍から言語を決定
            $language = $user->nationality === 'JP' ? 'ja' : 'en';
            $settings['language'] = $language;
            
            DB::table('users')
                ->where('user_id', $user->user_id)
                ->update(['settings' => json_encode($settings)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ロールバック時は言語設定を削除
        $users = DB::table('users')->get();
        
        foreach ($users as $user) {
            $settings = json_decode($user->settings ?? '{}', true);
            
            if (isset($settings['language'])) {
                unset($settings['language']);
                DB::table('users')
                    ->where('user_id', $user->user_id)
                    ->update(['settings' => json_encode($settings)]);
            }
        }
    }
};

