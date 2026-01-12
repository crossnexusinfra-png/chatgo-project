<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 既存のタイムスタンプをUTCに変換します。
     * 注意: このマイグレーションは、既存のデータが既にUTCで保存されている場合でも
     * 安全に実行できます（変更がないため）。
     */
    public function up(): void
    {
        // アプリケーションのタイムゾーンをUTCに設定
        date_default_timezone_set('UTC');
        
        // 注意: 既存のデータがJST（UTC+9）で保存されている場合を想定して変換します。
        // 既存データが既にUTCで保存されている場合は、このマイグレーションを実行しても
        // データは変更されません（UTCとして解釈してUTCに変換するため）。
        
        // threadsテーブルのcreated_atとupdated_atをUTCに変換
        // 既存データをJSTとして解釈し、UTCに変換します
        $threads = DB::table('threads')->get();
        foreach ($threads as $thread) {
            if ($thread->created_at) {
                // 既存の日時をJSTとして解釈し、UTCに変換
                // タイムゾーン情報がない場合、JST（Asia/Tokyo）として解釈
                $createdAt = Carbon::parse($thread->created_at, 'Asia/Tokyo')->utc();
                DB::table('threads')
                    ->where('thread_id', $thread->thread_id)
                    ->update(['created_at' => $createdAt->format('Y-m-d H:i:s')]);
            }
            if ($thread->updated_at) {
                $updatedAt = Carbon::parse($thread->updated_at, 'Asia/Tokyo')->utc();
                DB::table('threads')
                    ->where('thread_id', $thread->thread_id)
                    ->update(['updated_at' => $updatedAt->format('Y-m-d H:i:s')]);
            }
        }
        
        // responsesテーブルのcreated_atとupdated_atをUTCに変換
        $responses = DB::table('responses')->get();
        foreach ($responses as $response) {
            if ($response->created_at) {
                $createdAt = Carbon::parse($response->created_at, 'Asia/Tokyo')->utc();
                DB::table('responses')
                    ->where('response_id', $response->response_id)
                    ->update(['created_at' => $createdAt->format('Y-m-d H:i:s')]);
            }
            if ($response->updated_at) {
                $updatedAt = Carbon::parse($response->updated_at, 'Asia/Tokyo')->utc();
                DB::table('responses')
                    ->where('response_id', $response->response_id)
                    ->update(['updated_at' => $updatedAt->format('Y-m-d H:i:s')]);
            }
        }
        
        // admin_messagesテーブルのcreated_at、updated_at、published_atをUTCに変換
        $adminMessages = DB::table('admin_messages')->get();
        foreach ($adminMessages as $message) {
            if ($message->created_at) {
                $createdAt = Carbon::parse($message->created_at, 'Asia/Tokyo')->utc();
                DB::table('admin_messages')
                    ->where('id', $message->id)
                    ->update(['created_at' => $createdAt->format('Y-m-d H:i:s')]);
            }
            if ($message->updated_at) {
                $updatedAt = Carbon::parse($message->updated_at, 'Asia/Tokyo')->utc();
                DB::table('admin_messages')
                    ->where('id', $message->id)
                    ->update(['updated_at' => $updatedAt->format('Y-m-d H:i:s')]);
            }
            if ($message->published_at) {
                $publishedAt = Carbon::parse($message->published_at, 'Asia/Tokyo')->utc();
                DB::table('admin_messages')
                    ->where('id', $message->id)
                    ->update(['published_at' => $publishedAt->format('Y-m-d H:i:s')]);
            }
        }
        
        // usersテーブルのcreated_atとupdated_atをUTCに変換
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            if ($user->created_at) {
                $createdAt = Carbon::parse($user->created_at, 'Asia/Tokyo')->utc();
                DB::table('users')
                    ->where('user_id', $user->user_id)
                    ->update(['created_at' => $createdAt->format('Y-m-d H:i:s')]);
            }
            if ($user->updated_at) {
                $updatedAt = Carbon::parse($user->updated_at, 'Asia/Tokyo')->utc();
                DB::table('users')
                    ->where('user_id', $user->user_id)
                    ->update(['updated_at' => $updatedAt->format('Y-m-d H:i:s')]);
            }
        }
        
        // thread_accessesテーブルのaccessed_atをUTCに変換
        $accesses = DB::table('thread_accesses')->get();
        foreach ($accesses as $access) {
            if ($access->accessed_at) {
                $accessedAt = Carbon::parse($access->accessed_at, 'Asia/Tokyo')->utc();
                DB::table('thread_accesses')
                    ->where('access_id', $access->access_id)
                    ->update(['accessed_at' => $accessedAt->format('Y-m-d H:i:s')]);
            }
        }
        
        // reportsテーブルのcreated_at、updated_at、approved_atをUTCに変換
        $reports = DB::table('reports')->get();
        foreach ($reports as $report) {
            if ($report->created_at) {
                $createdAt = Carbon::parse($report->created_at, 'Asia/Tokyo')->utc();
                DB::table('reports')
                    ->where('report_id', $report->report_id)
                    ->update(['created_at' => $createdAt->format('Y-m-d H:i:s')]);
            }
            if ($report->updated_at) {
                $updatedAt = Carbon::parse($report->updated_at, 'Asia/Tokyo')->utc();
                DB::table('reports')
                    ->where('report_id', $report->report_id)
                    ->update(['updated_at' => $updatedAt->format('Y-m-d H:i:s')]);
            }
            if ($report->approved_at) {
                $approvedAt = Carbon::parse($report->approved_at, 'Asia/Tokyo')->utc();
                DB::table('reports')
                    ->where('report_id', $report->report_id)
                    ->update(['approved_at' => $approvedAt->format('Y-m-d H:i:s')]);
            }
        }
    }

    /**
     * Reverse the migrations.
     * 
     * 注意: このマイグレーションのロールバックは推奨されません。
     * 既存のデータがUTCで保存されている場合、元のタイムゾーンに戻すことは困難です。
     */
    public function down(): void
    {
        // ロールバックは実装しません
        // データの整合性を保つため、UTCのままにしておきます
    }
};

