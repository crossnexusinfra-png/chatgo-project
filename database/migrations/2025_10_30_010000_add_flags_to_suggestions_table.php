<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            $table->boolean('completed')->default(false)->index();
            $table->boolean('starred')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            $table->dropColumn(['completed', 'starred']);
        });
    }
};


