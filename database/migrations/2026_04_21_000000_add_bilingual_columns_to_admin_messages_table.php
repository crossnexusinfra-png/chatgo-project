<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('admin_messages', 'title_ja')) {
                $table->string('title_ja')->nullable()->after('title');
            }
            if (!Schema::hasColumn('admin_messages', 'title_en')) {
                $table->string('title_en')->nullable()->after('title_ja');
            }
            if (!Schema::hasColumn('admin_messages', 'body_ja')) {
                $table->text('body_ja')->nullable()->after('body');
            }
            if (!Schema::hasColumn('admin_messages', 'body_en')) {
                $table->text('body_en')->nullable()->after('body_ja');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_messages', function (Blueprint $table) {
            $dropColumns = [];
            foreach (['title_ja', 'title_en', 'body_ja', 'body_en'] as $column) {
                if (Schema::hasColumn('admin_messages', $column)) {
                    $dropColumns[] = $column;
                }
            }
            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};

