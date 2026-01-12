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
        Schema::create('user_invites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inviter_id');
            $table->unsignedBigInteger('invitee_id');
            $table->string('invite_code', 20);
            $table->boolean('coins_given')->default(false);
            $table->boolean('friend_request_auto_created')->default(false);
            $table->timestamp('invited_at')->useCurrent();
            $table->timestamps();

            $table->foreign('inviter_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('invitee_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->unique('invitee_id');
            $table->index('inviter_id');
            $table->index('invite_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_invites');
    }
};

