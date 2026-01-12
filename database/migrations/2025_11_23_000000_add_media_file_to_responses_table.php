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
        // PostgreSQLでカラムが存在するかチェックしてから追加
        $columns = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'responses' AND column_name IN ('media_file', 'media_type')");
        $existingColumns = array_column($columns, 'column_name');
        
        if (!in_array('media_file', $existingColumns)) {
            DB::statement('ALTER TABLE responses ADD COLUMN media_file VARCHAR(255) NULL');
        }
        
        if (!in_array('media_type', $existingColumns)) {
            DB::statement('ALTER TABLE responses ADD COLUMN media_type VARCHAR(255) NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropColumn(['media_file', 'media_type']);
        });
    }
};

