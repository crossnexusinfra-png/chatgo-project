<?php

use App\Services\ReportTargetStateService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            if (!Schema::hasColumn('threads', 'report_restricted')) {
                $table->boolean('report_restricted')->default(false);
            }
            if (!Schema::hasColumn('threads', 'deleted_by_report')) {
                $table->boolean('deleted_by_report')->default(false);
            }
            if (!Schema::hasColumn('threads', 'report_restriction_reasons')) {
                $table->json('report_restriction_reasons')->nullable();
            }
        });

        Schema::table('responses', function (Blueprint $table) {
            if (!Schema::hasColumn('responses', 'report_restricted')) {
                $table->boolean('report_restricted')->default(false);
            }
            if (!Schema::hasColumn('responses', 'deleted_by_report')) {
                $table->boolean('deleted_by_report')->default(false);
            }
            if (!Schema::hasColumn('responses', 'report_restriction_reasons')) {
                $table->json('report_restriction_reasons')->nullable();
            }
        });

        ReportTargetStateService::backfill();
    }

    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            foreach (['report_restricted', 'deleted_by_report', 'report_restriction_reasons'] as $column) {
                if (Schema::hasColumn('threads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::table('responses', function (Blueprint $table) {
            foreach (['report_restricted', 'deleted_by_report', 'report_restriction_reasons'] as $column) {
                if (Schema::hasColumn('responses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
