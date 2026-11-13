<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (Schema::hasColumn('reports', 'reporter_weight')) {
                $table->dropColumn('reporter_weight');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (!Schema::hasColumn('reports', 'reporter_weight')) {
                $table->decimal('reporter_weight', 4, 2)->nullable()->after('out_count');
            }
        });
    }
};
