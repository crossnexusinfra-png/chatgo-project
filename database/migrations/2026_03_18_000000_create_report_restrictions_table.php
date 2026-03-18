<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_restrictions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // 制限を受けたユーザー（投稿者/プロフィール所有者）
            $table->string('type'); // thread|response|profile
            $table->unsignedBigInteger('thread_id')->nullable();
            $table->unsignedBigInteger('response_id')->nullable();
            $table->unsignedBigInteger('reported_user_id')->nullable(); // profile の場合に一致（user_id と同値）
            $table->string('status')->default('active'); // active|acknowledged|cleared
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedBigInteger('admin_message_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['type', 'status']);
            $table->index(['thread_id', 'status']);
            $table->index(['response_id', 'status']);
            $table->index(['reported_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_restrictions');
    }
};

