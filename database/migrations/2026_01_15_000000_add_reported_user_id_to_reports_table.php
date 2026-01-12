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
        Schema::table('reports', function (Blueprint $table) {
            $table->foreignId('reported_user_id')->nullable()->after('response_id')->constrained('users', 'user_id')->onDelete('cascade');
            
            // 同じユーザーに複数回通報できないようにするためのユニーク制約
            $table->unique(['user_id', 'reported_user_id'], 'unique_user_reported_user_report');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropUnique('unique_user_reported_user_report');
            $table->dropForeign(['reported_user_id']);
            $table->dropColumn('reported_user_id');
        });
    }
};
