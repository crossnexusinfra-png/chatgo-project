<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ログインボーナスを居住地ローカル日・居住地コードで識別するため。
     */
    public function up(): void
    {
        Schema::table('consecutive_login_rewards', function (Blueprint $table) {
            $table->date('reward_local_date')->nullable()->after('reward_date');
            $table->string('residence_at_reward', 16)->nullable()->after('reward_local_date');
            $table->index(['user_id', 'reward_local_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consecutive_login_rewards', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'reward_local_date']);
            $table->dropColumn(['reward_local_date', 'residence_at_reward']);
        });
    }
};
