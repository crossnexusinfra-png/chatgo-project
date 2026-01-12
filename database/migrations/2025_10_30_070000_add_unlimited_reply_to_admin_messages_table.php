<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            $table->boolean('unlimited_reply')->default(false)->after('allows_reply');
        });
    }

    public function down(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            $table->dropColumn('unlimited_reply');
        });
    }
};

