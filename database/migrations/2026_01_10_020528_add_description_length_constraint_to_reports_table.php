<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        // 既存データを制約に適合させる
        // reports.description: 300文字を超える場合は300文字に切り詰める
        $reports = DB::table('reports')->whereNotNull('description')->get();
        foreach ($reports as $report) {
            if (mb_strlen($report->description) > 300) {
                $truncatedDescription = mb_substr($report->description, 0, 300);
                DB::table('reports')
                    ->where('report_id', $report->report_id)
                    ->update(['description' => $truncatedDescription]);
            }
        }
        
        // reports.descriptionの長さ制限（300文字）のCHECK制約
        if ($driver === 'mysql') {
            // MySQL 8.0.16以降でCHECK制約をサポート
            DB::statement('ALTER TABLE reports ADD CONSTRAINT reports_description_length CHECK (CHAR_LENGTH(description) <= 300 OR description IS NULL)');
        } elseif ($driver === 'sqlite') {
            // SQLiteでは既存のテーブルにCHECK制約を追加できないため、
            // アプリケーション層での検証に依存
        } elseif ($driver === 'pgsql') {
            // PostgreSQLではCHECK制約をサポート
            DB::statement('ALTER TABLE reports ADD CONSTRAINT reports_description_length CHECK (CHAR_LENGTH(description) <= 300 OR description IS NULL)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        // CHECK制約の削除
        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE reports DROP CONSTRAINT reports_description_length');
            } catch (\Exception $e) {
                // 制約が存在しない場合は無視
            }
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE reports DROP CONSTRAINT IF EXISTS reports_description_length');
        }
    }
};

