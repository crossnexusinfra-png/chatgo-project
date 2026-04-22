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
            if (!Schema::hasColumn('admin_messages', 'is_manual_sent')) {
                $table->boolean('is_manual_sent')->nullable()->after('is_auto_sent')->comment('お知らせ送信画面からの手動送信');
            }
            if (!Schema::hasColumn('admin_messages', 'is_from_template')) {
                $table->boolean('is_from_template')->nullable()->after('is_manual_sent')->comment('手動送信時にテンプレートから適用して送信');
            }
        });

        if (Schema::hasColumn('admin_messages', 'is_manual_sent')) {
            DB::table('admin_messages')
                ->whereNull('is_manual_sent')
                ->whereNotNull('published_at')
                ->whereNull('parent_message_id')
                ->where(function ($q) {
                    $q->whereNull('is_auto_sent')->orWhere('is_auto_sent', false);
                })
                ->update(['is_manual_sent' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            if (Schema::hasColumn('admin_messages', 'is_from_template')) {
                $table->dropColumn('is_from_template');
            }
            if (Schema::hasColumn('admin_messages', 'is_manual_sent')) {
                $table->dropColumn('is_manual_sent');
            }
        });
    }
};
