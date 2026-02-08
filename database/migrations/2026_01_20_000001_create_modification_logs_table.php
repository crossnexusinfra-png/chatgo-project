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
        Schema::create('modification_logs', function (Blueprint $table) {
            $table->id('modification_log_id');
            $table->string('entity_type', 20)->comment('エンティティタイプ: user, thread');
            $table->unsignedBigInteger('entity_id')->comment('エンティティID');
            $table->string('operation_type', 50)->comment('操作タイプ: create, update, delete, hide, unhide, freeze, unfreeze, permanent_ban, restore');
            $table->string('field_name', 100)->nullable()->comment('変更されたフィールド名（update操作の場合）');
            $table->text('old_value')->nullable()->comment('変更前の値（テキスト形式）');
            $table->text('new_value')->nullable()->comment('変更後の値（テキスト形式）');
            $table->unsignedBigInteger('performed_by_user_id')->nullable()->comment('操作を実行したユーザーID');
            $table->string('ip_address', 45)->nullable()->comment('操作を実行したIPアドレス');
            $table->text('user_agent')->nullable()->comment('操作を実行したユーザーエージェント');
            $table->text('description')->nullable()->comment('操作の説明・理由');
            $table->json('additional_data')->nullable()->comment('追加データ（JSON形式）');
            $table->timestamp('performed_at')->useCurrent()->comment('操作実行日時');
            $table->timestamp('created_at')->useCurrent()->comment('レコード作成日時');
            $table->timestamp('updated_at')->nullable()->comment('レコード更新日時');

            // インデックス
            $table->index(['entity_type', 'entity_id']);
            $table->index(['entity_type', 'entity_id', 'operation_type']);
            $table->index('operation_type');
            $table->index('performed_by_user_id');
            $table->index('performed_at');
            $table->index('ip_address');

            // 外部キー制約
            $table->foreign('performed_by_user_id')
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
        Schema::dropIfExists('modification_logs');
    }
};
