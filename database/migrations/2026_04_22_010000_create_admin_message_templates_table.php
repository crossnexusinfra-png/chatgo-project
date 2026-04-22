<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_message_templates')) {
            return;
        }

        Schema::create('admin_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title_ja')->nullable();
            $table->string('title_en')->nullable();
            $table->text('body_ja');
            $table->text('body_en')->nullable();
            $table->unsignedInteger('coin_amount')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_message_templates');
    }
};
