<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->uuid('request_id')->nullable()->index()->after('id');
            $table->uuid('event_id')->nullable()->index()->after('request_id');
            $table->string('method', 12)->nullable()->after('type');
            $table->unsignedSmallInteger('status_code')->nullable()->after('method');
            $table->string('source', 32)->default('app')->after('status_code');
        });
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropColumn(['request_id', 'event_id', 'method', 'status_code', 'source']);
        });
    }
};
