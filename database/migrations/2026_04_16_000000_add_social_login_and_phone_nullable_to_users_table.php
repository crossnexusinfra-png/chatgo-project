<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique()->change();
            $table->string('x_provider_id')->nullable()->unique()->after('phone');
            $table->string('google_provider_id')->nullable()->unique()->after('x_provider_id');
            $table->string('apple_provider_id')->nullable()->unique()->after('google_provider_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['x_provider_id', 'google_provider_id', 'apple_provider_id']);
            $table->string('phone', 20)->nullable(false)->unique()->change();
        });
    }
};
