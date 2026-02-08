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
        Schema::dropIfExists('modification_logs');
        Schema::dropIfExists('change_logs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 元に戻す場合は、元のマイグレーションファイルを再実行する必要があります
        // ここでは空の実装とします
    }
};
