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
        // 既存のユーザー名が10文字を超える場合は切り捨て
        $users = DB::table('users')->get();
        
        foreach ($users as $user) {
            if (mb_strlen($user->username) > 10) {
                $truncatedUsername = mb_substr($user->username, 0, 10);
                DB::table('users')
                    ->where('user_id', $user->user_id)
                    ->update(['username' => $truncatedUsername]);
            }
        }
        
        // usernameカラムの長さを10文字に制限（unique制約は既に存在するため再指定不要）
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 10)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 255)->change();
        });
    }
};

