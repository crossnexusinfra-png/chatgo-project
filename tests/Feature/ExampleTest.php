<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
        $location = $response->headers->get('Location') ?? '';
        $this->assertMatchesRegularExpression('#/(ja|en)$#', parse_url($location, PHP_URL_PATH) ?: $location);
    }
}
