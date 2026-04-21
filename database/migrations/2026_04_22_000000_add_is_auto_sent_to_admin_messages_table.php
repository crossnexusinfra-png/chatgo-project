<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('admin_messages', 'is_auto_sent')) {
                $table->boolean('is_auto_sent')->nullable()->after('coin_amount')->comment('システム自動送信のお知らせ（手動配信はfalse）');
            }
        });

        // 既存データ: title_key で判別できる自動送信のみ true にする（NULL は従来どおり手動扱い）
        if (Schema::hasColumn('admin_messages', 'is_auto_sent')) {
            DB::table('admin_messages')
                ->whereNull('is_auto_sent')
                ->whereIn('title_key', [
                    'r18_change_request_title',
                    'report_restriction_review_title',
                    'report_restriction_ack_title',
                    'suggestion_received_title',
                ])
                ->update(['is_auto_sent' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            if (Schema::hasColumn('admin_messages', 'is_auto_sent')) {
                $table->dropColumn('is_auto_sent');
            }
        });
    }
};
