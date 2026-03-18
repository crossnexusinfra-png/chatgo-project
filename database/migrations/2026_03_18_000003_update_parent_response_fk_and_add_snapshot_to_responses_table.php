<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_original_response_id')->nullable()->after('parent_response_id');
            $table->string('parent_snapshot_username', 100)->nullable()->after('parent_original_response_id');
            $table->text('parent_snapshot_body')->nullable()->after('parent_snapshot_username');

            $table->index('parent_original_response_id', 'responses_parent_original_response_id_index');
        });

        Schema::table('responses', function (Blueprint $table) {
            // 返信元が削除されても返信レスは残す（表示はスナップショットで補完）
            $table->dropForeign(['parent_response_id']);
            $table->foreign('parent_response_id')
                ->references('response_id')
                ->on('responses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropForeign(['parent_response_id']);
            $table->foreign('parent_response_id')
                ->references('response_id')
                ->on('responses')
                ->cascadeOnDelete();
        });

        Schema::table('responses', function (Blueprint $table) {
            $table->dropIndex('responses_parent_original_response_id_index');
            $table->dropColumn(['parent_original_response_id', 'parent_snapshot_username', 'parent_snapshot_body']);
        });
    }
};

