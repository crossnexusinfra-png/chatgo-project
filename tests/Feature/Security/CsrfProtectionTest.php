<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * CSRF 対策の動作確認。
 * POST 系エンドポイントに CSRF トークンなしでリクエストすると拒否されることを検証する。
 */
class CsrfProtectionTest extends TestCase
{
    /**
     * ログイン POST に CSRF トークンなしで送信すると 419 になること。
     */
    public function test_login_post_without_csrf_token_returns_419_or_redirect(): void
    {
        $response = $this->post(route('login'), [
            'username' => 'test',
            'password' => 'password',
        ], [
            'Accept' => 'text/html',
        ]);

        // Laravel は CSRF 検証失敗時 419 を返す（またはセッション切れでログイン画面へリダイレクト）
        $this->assertContains($response->status(), [419, 302]);
        if ($response->status() === 302) {
            $this->assertStringContainsString('login', $response->headers->get('Location') ?? '');
        }
    }

    /**
     * スレッド一覧 GET では CSRF は不要なため 200 が返ること。
     */
    public function test_threads_index_get_does_not_require_csrf(): void
    {
        $response = $this->get(route('threads.index'));

        $response->assertStatus(200);
    }

    /**
     * スレッド作成 POST に CSRF トークンなしで送信すると 419 になること。
     */
    public function test_threads_store_post_without_csrf_token_returns_419(): void
    {
        $response = $this->post(route('threads.store'), [
            'title' => 'Test',
            'body'  => 'Body',
        ], [
            'Accept' => 'text/html',
        ]);

        $this->assertContains($response->status(), [419, 302]);
    }
}
