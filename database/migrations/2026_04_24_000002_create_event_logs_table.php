<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->index();
            $table->uuid('request_id')->nullable()->index();
            $table->string('event_type', 100);
            $table->string('source', 32)->default('server');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('path')->nullable();
            $table->string('ip')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_logs');
    }
};
