<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            $table->string('welcome_type', 20)
                ->nullable()
                ->after('is_welcome')
                ->comment('初回登録時テンプレート種別: normal/google/phone');
        });

        DB::table('admin_messages')
            ->where('is_welcome', true)
            ->whereNull('welcome_type')
            ->update(['welcome_type' => 'normal']);
    }

    public function down(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            $table->dropColumn('welcome_type');
        });
    }
};
