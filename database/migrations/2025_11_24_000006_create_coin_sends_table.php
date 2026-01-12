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
        Schema::create('coin_sends', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');
            $table->unsignedInteger('coins');
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('next_available_at')->nullable();
            $table->timestamps();

            $table->foreign('from_user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('to_user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index('from_user_id');
            $table->index('to_user_id');
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coin_sends');
    }
};

