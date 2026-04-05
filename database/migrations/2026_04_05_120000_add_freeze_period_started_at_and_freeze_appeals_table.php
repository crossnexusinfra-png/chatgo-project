<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('freeze_period_started_at')->nullable()->after('is_permanently_banned');
        });

        DB::table('users')
            ->where(function ($q) {
                $q->where('is_permanently_banned', true)
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('frozen_until')
                            ->where('frozen_until', '>', now());
                    });
            })
            ->whereNull('freeze_period_started_at')
            ->update(['freeze_period_started_at' => now()]);

        Schema::create('freeze_appeals', function (Blueprint $table) {
            $table->bigIncrements('freeze_appeal_id');
            $table->unsignedBigInteger('user_id');
            $table->text('message');
            $table->decimal('out_count_snapshot', 8, 2);
            $table->timestamp('frozen_until_snapshot')->nullable();
            $table->boolean('is_permanent_snapshot')->default(false);
            $table->timestamp('freeze_period_started_at');
            $table->string('status', 20)->default('pending');
            $table->decimal('out_count_reduced', 8, 2)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'freeze_period_started_at'], 'freeze_appeals_user_period_unique');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freeze_appeals');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('freeze_period_started_at');
        });
    }
};
