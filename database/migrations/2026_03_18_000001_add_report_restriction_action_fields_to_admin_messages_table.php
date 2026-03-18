<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('admin_messages', 'thread_id')) {
                $table->unsignedBigInteger('thread_id')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('admin_messages', 'response_id')) {
                $table->unsignedBigInteger('response_id')->nullable()->after('thread_id');
            }
            if (!Schema::hasColumn('admin_messages', 'reported_user_id')) {
                $table->unsignedBigInteger('reported_user_id')->nullable()->after('response_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            if (Schema::hasColumn('admin_messages', 'reported_user_id')) {
                $table->dropColumn('reported_user_id');
            }
            if (Schema::hasColumn('admin_messages', 'response_id')) {
                $table->dropColumn('response_id');
            }
            // thread_id は既存で使われている可能性が高いので drop しない
        });
    }
};

