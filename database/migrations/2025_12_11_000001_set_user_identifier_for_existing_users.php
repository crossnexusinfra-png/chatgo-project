<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 既存ユーザーにランダムなuser_identifierを設定
        $users = DB::table('users')->whereNull('user_identifier')->get();
        
        foreach ($users as $user) {
            $userIdentifier = $this->generateUniqueUserIdentifier();
            DB::table('users')
                ->where('user_id', $user->user_id)
                ->update(['user_identifier' => $userIdentifier]);
        }
        
        // user_identifierをNOT NULLに変更（unique制約は既に存在するため再指定不要）
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_identifier', 15)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_identifier', 15)->nullable()->change();
        });
    }

    /**
     * ユニークなuser_identifierを生成
     */
    private function generateUniqueUserIdentifier(): string
    {
        do {
            // 5-15文字のランダムな文字列を生成（小文字英語とアンダーバーのみ）
            $length = random_int(5, 15);
            $userIdentifier = '';
            $chars = 'abcdefghijklmnopqrstuvwxyz_';
            
            for ($i = 0; $i < $length; $i++) {
                $userIdentifier .= $chars[random_int(0, strlen($chars) - 1)];
            }
            
            // 既に存在するかチェック
            $exists = DB::table('users')->where('user_identifier', $userIdentifier)->exists();
        } while ($exists);
        
        return $userIdentifier;
    }
};

