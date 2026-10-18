<?php

namespace Tests\Unit;

use App\Models\Thread;
use Tests\TestCase;

class ThreadR18ContentTest extends TestCase
{
    public function test_r18_flag_is_adult_content(): void
    {
        $thread = new Thread(['is_r18' => true, 'tag' => '雑談']);

        $this->assertTrue($thread->isR18());
        $this->assertTrue($thread->isR18Content());
    }

    public function test_r18_tag_is_adult_content_without_flag(): void
    {
        $thread = new Thread([
            'is_r18' => false,
            'tag' => '性体験談・性的嗜好・フェティシズム',
        ]);

        $this->assertFalse($thread->isR18());
        $this->assertTrue($thread->isR18Content());
    }

    public function test_general_thread_is_not_adult_content(): void
    {
        $thread = new Thread(['is_r18' => false, 'tag' => '雑談']);

        $this->assertFalse($thread->isR18());
        $this->assertFalse($thread->isR18Content());
    }
}
