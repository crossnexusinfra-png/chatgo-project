<?php

namespace Tests\Feature;

use Tests\TestCase;

class UnknownPublicRoutesReturn404Test extends TestCase
{
    public function test_unknown_tag_returns_404(): void
    {
        $this->get('/en/tag/not-a-real-tag')->assertNotFound();
        $this->get('/en/tag/'.rawurlencode('存在しないタグ'))->assertNotFound();
    }

    public function test_category_name_used_as_tag_returns_404(): void
    {
        $this->get('/en/tag/'.rawurlencode('生活・日常'))->assertNotFound();
    }

    public function test_english_tag_label_is_not_a_valid_tag_url(): void
    {
        $this->get('/en/tag/Housework')->assertNotFound();
    }

    public function test_unknown_tag_more_api_returns_404(): void
    {
        $this->getJson('/api/tag/not-a-real-tag/more')->assertNotFound();
        $this->get('/api/tag/not-a-real-tag/more', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertNotFound();
    }

    public function test_unknown_category_returns_404(): void
    {
        $this->get('/en/category/not-a-real-category')->assertNotFound();
        $this->getJson('/api/category/not-a-real-category/more')->assertNotFound();
    }

    public function test_unknown_article_slug_returns_404(): void
    {
        $this->get('/en/articles/not-a-real-article')->assertNotFound();
    }

    public function test_unmatched_path_returns_404(): void
    {
        $this->get('/this-path-does-not-exist')->assertNotFound();
    }
}
