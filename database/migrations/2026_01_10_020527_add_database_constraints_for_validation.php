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
        $driver = DB::getDriverName();
        
        // 既存データを制約に適合させる
        
        // users.bio: 100文字を超える場合は100文字に切り詰める
        $users = DB::table('users')->whereNotNull('bio')->get();
        foreach ($users as $user) {
            if (mb_strlen($user->bio) > 100) {
                $truncatedBio = mb_substr($user->bio, 0, 100);
                DB::table('users')
                    ->where('user_id', $user->user_id)
                    ->update(['bio' => $truncatedBio]);
            }
        }
        
        // users.phone: 20文字を超える場合は20文字に切り詰める
        $usersWithLongPhone = DB::table('users')->get();
        foreach ($usersWithLongPhone as $user) {
            if ($user->phone && mb_strlen($user->phone) > 20) {
                $truncatedPhone = mb_substr($user->phone, 0, 20);
                DB::table('users')
                    ->where('user_id', $user->user_id)
                    ->update(['phone' => $truncatedPhone]);
            }
        }
        
        // threads.tag: 100文字を超える場合は100文字に切り詰める
        $threads = DB::table('threads')->get();
        foreach ($threads as $thread) {
            if (mb_strlen($thread->tag) > 100) {
                $truncatedTag = mb_substr($thread->tag, 0, 100);
                DB::table('threads')
                    ->where('thread_id', $thread->thread_id)
                    ->update(['tag' => $truncatedTag]);
            }
        }
        
        // suggestions.message: 1000文字を超える場合は1000文字に切り詰める
        $suggestions = DB::table('suggestions')->get();
        foreach ($suggestions as $suggestion) {
            if (mb_strlen($suggestion->message) > 1000) {
                $truncatedMessage = mb_substr($suggestion->message, 0, 1000);
                DB::table('suggestions')
                    ->where('id', $suggestion->id)
                    ->update(['message' => $truncatedMessage]);
            }
        }
        
        // admin_messages.body: 2000文字を超える場合は2000文字に切り詰める
        $adminMessages = DB::table('admin_messages')->whereNotNull('body')->get();
        foreach ($adminMessages as $message) {
            if (mb_strlen($message->body) > 2000) {
                $truncatedBody = mb_substr($message->body, 0, 2000);
                DB::table('admin_messages')
                    ->where('id', $message->id)
                    ->update(['body' => $truncatedBody]);
            }
        }
        
        // users.nationality: 許可された値以外は'OTHER'に変更
        $allowedNationalities = ['JP', 'US', 'GB', 'CA', 'AU', 'OTHER'];
        DB::table('users')
            ->whereNotIn('nationality', $allowedNationalities)
            ->update(['nationality' => 'OTHER']);
        
        // users.residence: 許可された値以外は'OTHER'に変更
        $allowedResidences = ['JP', 'US', 'GB', 'CA', 'AU', 'OTHER'];
        DB::table('users')
            ->whereNotIn('residence', $allowedResidences)
            ->update(['residence' => 'OTHER']);
        
        // usersテーブルの制約追加
        Schema::table('users', function (Blueprint $table) use ($driver) {
            // phoneカラムの長さ制限を20文字に設定
            $table->string('phone', 20)->change();
        });
        
        // users.bioの長さ制限（100文字）のCHECK制約
        if ($driver === 'mysql') {
            // MySQL 8.0.16以降でCHECK制約をサポート
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_bio_length CHECK (CHAR_LENGTH(bio) <= 100 OR bio IS NULL)');
        } elseif ($driver === 'sqlite') {
            // SQLiteでは既存のテーブルにCHECK制約を追加できないため、
            // 新しいテーブルを作成してデータを移行する必要がある
            // ただし、これは複雑なため、アプリケーション層での検証に依存
        } elseif ($driver === 'pgsql') {
            // PostgreSQLではCHECK制約をサポート
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_bio_length CHECK (CHAR_LENGTH(bio) <= 100 OR bio IS NULL)');
        }
        
        // users.nationalityの値制限のCHECK制約
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_nationality_check CHECK (nationality IN ('JP', 'US', 'GB', 'CA', 'AU', 'OTHER'))");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_nationality_check CHECK (nationality IN ('JP', 'US', 'GB', 'CA', 'AU', 'OTHER'))");
        }
        
        // users.residenceの値制限のCHECK制約
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_residence_check CHECK (residence IN ('JP', 'US', 'GB', 'CA', 'AU', 'OTHER'))");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_residence_check CHECK (residence IN ('JP', 'US', 'GB', 'CA', 'AU', 'OTHER'))");
        }
        
        // threadsテーブルの制約追加
        Schema::table('threads', function (Blueprint $table) {
            // tagカラムの長さ制限を100文字に設定
            $table->string('tag', 100)->change();
        });
        
        // suggestionsテーブルの制約追加
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE suggestions ADD CONSTRAINT suggestions_message_length CHECK (CHAR_LENGTH(message) <= 1000)');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE suggestions ADD CONSTRAINT suggestions_message_length CHECK (CHAR_LENGTH(message) <= 1000)');
        }
        
        // admin_messagesテーブルの制約追加
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE admin_messages ADD CONSTRAINT admin_messages_body_length CHECK (CHAR_LENGTH(body) <= 2000 OR body IS NULL)');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE admin_messages ADD CONSTRAINT admin_messages_body_length CHECK (CHAR_LENGTH(body) <= 2000 OR body IS NULL)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        // usersテーブルの制約削除
        Schema::table('users', function (Blueprint $table) {
            // phoneカラムの長さ制限を元に戻す（デフォルトの255文字）
            $table->string('phone')->change();
        });
        
        // CHECK制約の削除
        if ($driver === 'mysql') {
            // MySQL 8.0.16以降でCHECK制約をサポート
            try {
                DB::statement('ALTER TABLE users DROP CONSTRAINT users_bio_length');
            } catch (\Exception $e) {
                // 制約が存在しない場合は無視
            }
            try {
                DB::statement('ALTER TABLE users DROP CONSTRAINT users_nationality_check');
            } catch (\Exception $e) {
                // 制約が存在しない場合は無視
            }
            try {
                DB::statement('ALTER TABLE users DROP CONSTRAINT users_residence_check');
            } catch (\Exception $e) {
                // 制約が存在しない場合は無視
            }
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_bio_length');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_nationality_check');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_residence_check');
        }
        
        // threadsテーブルの制約削除
        Schema::table('threads', function (Blueprint $table) {
            // tagカラムの長さ制限を元に戻す（デフォルトの255文字）
            $table->string('tag')->change();
        });
        
        // suggestionsテーブルの制約削除
        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE suggestions DROP CONSTRAINT suggestions_message_length');
            } catch (\Exception $e) {
                // 制約が存在しない場合は無視
            }
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE suggestions DROP CONSTRAINT IF EXISTS suggestions_message_length');
        }
        
        // admin_messagesテーブルの制約削除
        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE admin_messages DROP CONSTRAINT admin_messages_body_length');
            } catch (\Exception $e) {
                // 制約が存在しない場合は無視
            }
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE admin_messages DROP CONSTRAINT IF EXISTS admin_messages_body_length');
        }
    }
};
