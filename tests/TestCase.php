<?php

namespace Tests;

use App\Services\LanguageService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => LanguageService::fallbackLocale()]);
    }
}

