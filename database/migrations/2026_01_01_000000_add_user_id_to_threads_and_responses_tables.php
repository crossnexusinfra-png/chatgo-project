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
        // threadsテーブルにuser_idカラムを追加
        Schema::table('threads', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('user_name');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index('user_id');
        });

        // responsesテーブルにuser_idカラムを追加
        Schema::table('responses', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('user_name');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index('user_id');
        });

        // 既存データの移行: threadsテーブル
        // user_nameからusers.usernameを参照してuser_idを設定
        // PostgreSQL用の構文: UPDATE ... FROM ... WHERE
        DB::statement("
            UPDATE threads t
            SET user_id = u.user_id
            FROM users u
            WHERE t.user_name = u.username
            AND t.user_id IS NULL
        ");

        // 既存データの移行: responsesテーブル
        // user_nameからusers.usernameを参照してuser_idを設定
        // PostgreSQL用の構文: UPDATE ... FROM ... WHERE
        DB::statement("
            UPDATE responses r
            SET user_id = u.user_id
            FROM users u
            WHERE r.user_name = u.username
            AND r.user_id IS NULL
        ");

        // user_nameが一致しないレコードがないか確認
        $threadsWithoutUserId = DB::table('threads')->whereNull('user_id')->count();
        $responsesWithoutUserId = DB::table('responses')->whereNull('user_id')->count();
        
        if ($threadsWithoutUserId > 0 || $responsesWithoutUserId > 0) {
            // 削除されたユーザー用のデフォルトユーザーを取得または作成
            $deletedUser = DB::table('users')
                ->where('username', '削除されたユーザー')
                ->first();
            
            if (!$deletedUser) {
                // 削除されたユーザー用のユーザーが存在しない場合は作成
                // emailとphoneはUNIQUE制約があるため、ユニークな値を設定
                $timestamp = now()->timestamp;
                $randomSuffix = Str::random(6);
                
                // emailとphoneが重複しないように、既存のユーザーをチェック
                $email = 'deleted_' . $timestamp . '_' . $randomSuffix . '@system.local';
                $phone = '999' . substr($timestamp, -8); // 999で始まる11桁の電話番号
                
                // 重複チェック（念のため）
                while (DB::table('users')->where('email', $email)->exists()) {
                    $randomSuffix = Str::random(6);
                    $email = 'deleted_' . $timestamp . '_' . $randomSuffix . '@system.local';
                }
                
                while (DB::table('users')->where('phone', $phone)->exists()) {
                    $phone = '999' . substr(now()->timestamp, -8);
                }
                
                // user_identifierを生成（15文字以内、ユニーク）
                $userIdentifier = 'DELETED_' . substr($timestamp, -7); // 最大15文字
                
                // user_identifierが重複しないようにチェック
                while (DB::table('users')->where('user_identifier', $userIdentifier)->exists()) {
                    $userIdentifier = 'DELETED_' . substr(now()->timestamp, -7);
                }
                
                $deletedUserId = DB::table('users')->insertGetId([
                    'username' => '削除されたユーザー',
                    'user_identifier' => $userIdentifier,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => bcrypt(Str::random(32)), // ランダムなパスワード
                    'nationality' => 'Japan',
                    'residence' => 'Japan',
                    'is_verified' => false,
                    'coins' => 0,
                    'consecutive_login_days' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'user_id'); // 主キー名を指定
            } else {
                $deletedUserId = $deletedUser->user_id;
            }
            
            // user_nameが一致しないレコードにデフォルトユーザーIDを設定
            DB::table('threads')
                ->whereNull('user_id')
                ->update(['user_id' => $deletedUserId]);
            
            DB::table('responses')
                ->whereNull('user_id')
                ->update(['user_id' => $deletedUserId]);
            
            // ログに記録
            \Log::info('Migration: Assigned deleted user ID to records without matching username', [
                'threads_assigned' => $threadsWithoutUserId,
                'responses_assigned' => $responsesWithoutUserId,
                'deleted_user_id' => $deletedUserId,
            ]);
        }

        // user_idをNOT NULLに変更（既存データの移行が完了した後）
        Schema::table('threads', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('responses', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('responses', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};

