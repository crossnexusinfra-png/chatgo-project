<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 翻訳結果を1年間DBに保持。1年経過後は表示時に再翻訳する。
     */
    public function up(): void
    {
        Schema::create('translation_caches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thread_id')->nullable();
            $table->unsignedBigInteger('response_id')->nullable();
            $table->char('source_lang', 2); // JA / EN（元の言語）
            $table->char('target_lang', 2); // JA / EN（翻訳先＝表示言語）
            $table->text('translated_text');
            $table->timestamp('translated_at');
            $table->timestamps();

            // thread_id が設定されている行は (thread_id, target_lang) で一意
            $table->unique(['thread_id', 'target_lang'], 'translation_caches_thread_target_unique');
            // response_id が設定されている行は (response_id, target_lang) で一意
            $table->unique(['response_id', 'target_lang'], 'translation_caches_response_target_unique');
            $table->foreign('thread_id')->references('thread_id')->on('threads')->onDelete('cascade');
            $table->foreign('response_id')->references('response_id')->on('responses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translation_caches');
    }
};
