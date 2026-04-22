<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_user_enforcements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('enforcement_type', 32);
            $table->text('reason')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'released_at']);
            $table->index(['enforcement_type', 'released_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_enforcements');
    }
};

