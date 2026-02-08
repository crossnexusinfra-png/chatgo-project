<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('change_logs', function (Blueprint $table) {
            $table->id('change_log_id');
            $table->string('entity_type', 20)->comment('エンティティタイプ: user, thread');
            $table->unsignedBigInteger('entity_id')->comment('エンティティID');
            $table->string('action_type', 50)->comment('アクションタイプ: update, delete, hide, unhide, freeze, unfreeze, permanent_ban');
            $table->string('field_name', 100)->nullable()->comment('変更されたフィールド名');
            $table->json('old_value')->nullable()->comment('変更前の値');
            $table->json('new_value')->nullable()->comment('変更後の値');
            $table->unsignedBigInteger('changed_by_user_id')->nullable()->comment('変更を実行したユーザーID');
            $table->text('reason')->nullable()->comment('変更理由');
            $table->json('metadata')->nullable()->comment('追加情報（メタデータ）');
            $table->timestamp('created_at')->useCurrent()->comment('作成日時');
            $table->timestamp('updated_at')->nullable()->comment('更新日時');

            // インデックス
            $table->index(['entity_type', 'entity_id']);
            $table->index('action_type');
            $table->index('changed_by_user_id');
            $table->index('created_at');

            // 外部キー制約
            $table->foreign('changed_by_user_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('change_logs');
    }
};
