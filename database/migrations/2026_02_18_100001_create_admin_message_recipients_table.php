<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_message_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_message_id')->constrained('admin_messages')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->unique(['admin_message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_message_recipients');
    }
};
