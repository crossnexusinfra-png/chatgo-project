<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wal_recovery_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->index();
            $table->uuid('request_id')->nullable()->index();
            $table->string('database_driver', 32);
            $table->string('database_name')->nullable();
            $table->string('wal_lsn')->nullable()->index();
            $table->string('transaction_id')->nullable();
            $table->string('snapshot_reason', 120);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wal_recovery_logs');
    }
};
