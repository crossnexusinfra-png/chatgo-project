<?php

namespace Tests\Unit;

use App\Services\LanguageService;
use Tests\TestCase;

class LanguageServiceValidTagTest extends TestCase
{
    public function test_registered_tag_is_valid(): void
    {
        $this->assertTrue(LanguageService::isValidTag('家事'));
    }

    public function test_unknown_string_is_not_a_valid_tag(): void
    {
        $this->assertFalse(LanguageService::isValidTag('not-a-real-tag'));
        $this->assertFalse(LanguageService::isValidTag(''));
        $this->assertFalse(LanguageService::isValidTag('生活・日常'));
        $this->assertFalse(LanguageService::isValidTag('Housework'));
    }
}
