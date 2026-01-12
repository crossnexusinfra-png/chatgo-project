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
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $userId = auth()->check() ? auth()->user()->getAttribute('user_id') : null;
        
        $suggestion = Suggestion::create([
            'user_id' => $userId,
            'message' => $validated['message'],
        ]);

        // ログインユーザーには自動メッセージを送信
        if ($userId) {
            // キーベースでメッセージを作成（動的な内容は直接保存）
            $bodyTemplate = LanguageService::trans('suggestion_received_body');
            // \nを実際の改行文字に変換
            $bodyTemplate = str_replace('\\n', "\n", $bodyTemplate);
            $body = str_replace('{message}', $validated['message'], $bodyTemplate);
            
            AdminMessage::create([
                'title_key' => 'suggestion_received_title',
                'body' => $body, // 動的な内容を含むため直接保存
                'audience' => 'members',
                'user_id' => $userId,
                'published_at' => now(),
                'allows_reply' => false,
                'reply_used' => false,
                'unlimited_reply' => false,
            ]);
        }

        $language = LanguageService::getCurrentLanguage();
        $successMessage = LanguageService::trans('suggestion_success', $language);
        
        return back()->with('success', $successMessage);
    }
}


