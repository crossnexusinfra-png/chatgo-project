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
        // 英語圏と日本以外の国籍を「OTHER」に変更
        // 英語圏: US, GB, CA, AU
        // 日本: JP
        // その他: CN, KR, DE, FR, IT, ES などは「OTHER」に変更
        DB::table('users')
            ->whereNotIn('nationality', ['JP', 'US', 'GB', 'CA', 'AU', 'OTHER'])
            ->update(['nationality' => 'OTHER']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ロールバック時は何もしない（元の値に戻すことはできない）
    }
};

