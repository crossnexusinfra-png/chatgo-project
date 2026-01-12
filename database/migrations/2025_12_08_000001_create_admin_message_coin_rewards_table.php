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
        Schema::create('admin_message_coin_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('admin_message_id');
            $table->integer('coin_amount');
            $table->timestamp('received_at');
            $table->timestamps();

            // インデックス
            $table->index('user_id');
            $table->index('admin_message_id');
            
            // ユニーク制約：1ユーザーは1メッセージから1回だけコインを受け取れる
            $table->unique(['user_id', 'admin_message_id']);

            // 外部キー制約
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('admin_message_id')->references('id')->on('admin_messages')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_message_coin_rewards');
    }
};

