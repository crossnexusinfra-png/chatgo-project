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
        Schema::create('reports', function (Blueprint $table) {
            $table->id('report_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('thread_id')->nullable()->constrained('threads', 'thread_id')->onDelete('cascade');
            $table->foreignId('response_id')->nullable()->constrained('responses', 'response_id')->onDelete('cascade');
            $table->string('reason'); // 通報理由
            $table->text('description')->nullable(); // 自由記述欄（任意）
            $table->timestamps();
            
            // 同じスレッド/レスポンスに複数回通報できないようにするためのユニーク制約
            // thread_idがnullでない場合のみユニーク制約を適用
            $table->unique(['user_id', 'thread_id'], 'unique_user_thread_report');
            // response_idがnullでない場合のみユニーク制約を適用
            $table->unique(['user_id', 'response_id'], 'unique_user_response_report');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};

