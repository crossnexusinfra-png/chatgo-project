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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('frozen_until')->nullable()->after('is_verified');
            $table->integer('freeze_count')->default(0)->after('frozen_until');
            $table->boolean('is_permanently_banned')->default(false)->after('freeze_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['frozen_until', 'freeze_count', 'is_permanently_banned']);
        });
    }
};
