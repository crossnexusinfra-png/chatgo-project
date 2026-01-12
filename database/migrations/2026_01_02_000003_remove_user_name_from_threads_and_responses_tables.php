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
        // threadsテーブルからuser_nameカラムを削除
        Schema::table('threads', function (Blueprint $table) {
            $table->dropColumn('user_name');
        });

        // responsesテーブルからuser_nameカラムを削除
        Schema::table('responses', function (Blueprint $table) {
            $table->dropColumn('user_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ロールバック時はuser_nameカラムを再作成
        Schema::table('threads', function (Blueprint $table) {
            $table->string('user_name')->after('image_path');
        });

        Schema::table('responses', function (Blueprint $table) {
            $table->string('user_name')->after('body');
        });
    }
};

