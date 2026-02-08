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
        Schema::create('response_change_logs', function (Blueprint $table) {
            $table->id('log_id');
            $table->unsignedBigInteger('response_id')->comment('対象レスポンスID');
            $table->string('action_type', 50)->comment('アクションタイプ: delete, hide, unhide');
            $table->unsignedBigInteger('changed_by_user_id')->nullable()->comment('変更を実行したユーザーID');
            $table->string('ip_address', 45)->nullable()->comment('操作を実行したIPアドレス');
            $table->text('user_agent')->nullable()->comment('操作を実行したユーザーエージェント');
            $table->text('reason')->nullable()->comment('変更理由');
            $table->json('metadata')->nullable()->comment('追加情報（JSON形式）');
            $table->timestamp('changed_at')->useCurrent()->comment('変更日時');
            $table->timestamp('created_at')->useCurrent()->comment('レコード作成日時');
            $table->timestamp('updated_at')->nullable()->comment('レコード更新日時');

            // インデックス
            $table->index('response_id');
            $table->index('action_type');
            $table->index('changed_by_user_id');
            $table->index('changed_at');
            $table->index('ip_address');

            // 外部キー制約
            $table->foreign('response_id')
                ->references('response_id')
                ->on('responses')
                ->onDelete('cascade');
            
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
        Schema::dropIfExists('response_change_logs');
    }
};
