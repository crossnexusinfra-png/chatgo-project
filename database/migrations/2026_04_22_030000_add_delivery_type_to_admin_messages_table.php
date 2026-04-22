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
            if (!Schema::hasColumn('admin_messages', 'delivery_type')) {
                $table->string('delivery_type', 20)->nullable()->after('is_from_template')->comment('auto/template/manual');
            }
        });

        if (Schema::hasColumn('admin_messages', 'delivery_type')) {
            DB::table('admin_messages')
                ->whereNull('delivery_type')
                ->where('is_auto_sent', true)
                ->update(['delivery_type' => 'auto']);

            DB::table('admin_messages')
                ->whereNull('delivery_type')
                ->where('is_from_template', true)
                ->update(['delivery_type' => 'template']);

            DB::table('admin_messages')
                ->whereNull('delivery_type')
                ->where('is_manual_sent', true)
                ->update(['delivery_type' => 'manual']);
        }
    }

    public function down(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            if (Schema::hasColumn('admin_messages', 'delivery_type')) {
                $table->dropColumn('delivery_type');
            }
        });
    }
};
