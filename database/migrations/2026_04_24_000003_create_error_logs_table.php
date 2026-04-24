<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('error_id')->index();
            $table->uuid('request_id')->nullable()->index();
            $table->uuid('event_id')->nullable()->index();
            $table->string('source', 32)->default('server');
            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->string('error_type', 120)->nullable();
            $table->text('message');
            $table->string('path')->nullable();
            $table->string('method', 12)->nullable();
            $table->string('ip')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
