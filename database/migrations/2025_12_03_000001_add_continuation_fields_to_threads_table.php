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
        Schema::table('threads', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_thread_id')->nullable()->after('thread_id');
            $table->unsignedBigInteger('continuation_thread_id')->nullable()->after('parent_thread_id');
            
            $table->foreign('parent_thread_id')->references('thread_id')->on('threads')->onDelete('set null');
            $table->foreign('continuation_thread_id')->references('thread_id')->on('threads')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropForeign(['parent_thread_id']);
            $table->dropForeign(['continuation_thread_id']);
            $table->dropColumn(['parent_thread_id', 'continuation_thread_id']);
        });
    }
};
