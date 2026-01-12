<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_message_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreignId('admin_message_id')->constrained('admin_messages')->onDelete('cascade');
            $table->timestamp('read_at')->useCurrent();
            $table->timestamps();
            
            // usersテーブルのuser_idを参照（usersテーブルの主キーはuser_id）
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            
            // ユーザーごとに同じメッセージを重複して記録しないようにユニーク制約
            $table->unique(['user_id', 'admin_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_message_reads');
    }
};

