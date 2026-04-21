<?php

namespace App\Http\Controllers;

use App\Models\Suggestion;
use App\Models\AdminMessage;
use Illuminate\Http\Request;
use App\Services\LanguageService;

class SuggestionController extends Controller
{
    public function store(Request $request)
    {
        // 重複実行防止（認証時は user_id、未認証時はセッションID）
        $userKey = auth()->check() ? auth()->user()->user_id : session()->getId();
        $lock = \App\Services\DuplicateSubmissionLockService::acquire('suggestion.store', $userKey);
        if (!$lock) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return back()->withErrors(['message' => \App\Services\LanguageService::trans('duplicate_submission', $lang)]);
        }
        try {

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        // 改善要望本文: HTMLタグを除去して保存（XSS等の防御）
        $message = mb_substr(strip_tags($validated['message']), 0, 1000);

        $userId = auth()->check() ? auth()->user()->getAttribute('user_id') : null;
        
        $suggestion = Suggestion::create([
            'user_id' => $userId,
            'message' => $message,
        ]);

        // ログインユーザーには自動メッセージを送信
        if ($userId) {
            // キーベースでメッセージを作成（動的な内容は直接保存）
            $bodyTemplate = LanguageService::trans('suggestion_received_body');
            // \nを実際の改行文字に変換
            $bodyTemplate = str_replace('\\n', "\n", $bodyTemplate);
            $body = str_replace('{message}', $message, $bodyTemplate);
            
            AdminMessage::create([
                'title_key' => 'suggestion_received_title',
                'body' => $body, // 動的な内容を含むため直接保存
                'audience' => 'members',
                'user_id' => $userId,
                'published_at' => now(),
                'allows_reply' => false,
                'reply_used' => false,
                'unlimited_reply' => false,
                'is_auto_sent' => true,
            ]);
        }

        $language = LanguageService::getCurrentLanguage();
        $successMessage = LanguageService::trans('suggestion_success', $language);
        
        return back()->with('success', $successMessage);
        } finally {
            $lock->release();
        }
    }
}


