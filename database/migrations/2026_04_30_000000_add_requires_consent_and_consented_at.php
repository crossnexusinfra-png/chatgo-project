<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('admin_messages') && !Schema::hasColumn('admin_messages', 'requires_consent')) {
            Schema::table('admin_messages', function (Blueprint $table) {
                $table->boolean('requires_consent')->default(false)->after('reply_used');
            });
        }

        if (Schema::hasTable('admin_message_reads') && !Schema::hasColumn('admin_message_reads', 'consented_at')) {
            Schema::table('admin_message_reads', function (Blueprint $table) {
                $table->timestamp('consented_at')->nullable()->after('read_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('admin_messages', 'requires_consent')) {
            Schema::table('admin_messages', function (Blueprint $table) {
                $table->dropColumn('requires_consent');
            });
        }
        if (Schema::hasColumn('admin_message_reads', 'consented_at')) {
            Schema::table('admin_message_reads', function (Blueprint $table) {
                $table->dropColumn('consented_at');
            });
        }
    }
};
