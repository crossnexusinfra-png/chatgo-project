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
        Schema::table('responses', function (Blueprint $table) {
            $table->foreignId('parent_response_id')->nullable()->after('thread_id')
                ->constrained('responses', 'response_id')->onDelete('cascade');
            $table->integer('reply_level')->default(0)->after('parent_response_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropForeign(['parent_response_id']);
            $table->dropColumn(['parent_response_id', 'reply_level']);
        });
    }
};
