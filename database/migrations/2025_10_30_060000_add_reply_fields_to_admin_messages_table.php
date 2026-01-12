<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('audience');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->boolean('allows_reply')->default(false)->after('user_id');
            $table->boolean('reply_used')->default(false)->after('allows_reply');
            $table->unsignedBigInteger('parent_message_id')->nullable()->after('reply_used');
            $table->foreign('parent_message_id')->references('id')->on('admin_messages')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['parent_message_id']);
            $table->dropColumn(['user_id', 'allows_reply', 'reply_used', 'parent_message_id']);
        });
    }
};

