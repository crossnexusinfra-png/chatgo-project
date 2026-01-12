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
        Schema::create('thread_interactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('other_user_id');
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('total_characters')->default(0);
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();

            $table->foreign('thread_id')->references('thread_id')->on('threads')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('other_user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->unique(['thread_id', 'user_id', 'other_user_id']);
            $table->index('user_id');
            $table->index('other_user_id');
            $table->index('thread_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thread_interactions');
    }
};

