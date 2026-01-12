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
        // settingsカラムを削除（languageカラムに移行済み）
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ロールバック時はsettingsカラムを再作成
        Schema::table('users', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('bio');
        });
    }
};

