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
            $table->string('username')->unique()->after('name');
            $table->string('phone')->unique()->after('email');
            $table->string('nationality', 2)->after('phone');
            $table->string('residence', 2)->after('nationality');
            $table->string('sms_verification_code', 6)->nullable()->after('residence');
            $table->timestamp('sms_verified_at')->nullable()->after('sms_verification_code');
            $table->string('email_verification_code', 6)->nullable()->after('sms_verified_at');
            $table->timestamp('email_verified_at')->nullable()->after('email_verification_code');
            $table->boolean('is_verified')->default(false)->after('email_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'phone',
                'nationality',
                'residence',
                'sms_verification_code',
                'sms_verified_at',
                'email_verification_code',
                'email_verified_at',
                'is_verified'
            ]);
        });
    }
};
