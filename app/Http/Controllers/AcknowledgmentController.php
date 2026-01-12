<?php

namespace App\Http\Controllers;

use App\Models\Thread;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AcknowledgmentController extends Controller
{
    /**
     * スレッドの制限を了承する
     *
     * @param  int  $threadId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function acknowledgeThread($threadId)
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        
        $thread = Thread::findOrFail($threadId);
        
        // IDOR防止: R18スレッドの閲覧権限をチェック（18歳未満のユーザーは了承不可）
        $currentUser = auth()->user();
        if (!\Illuminate\Support\Facades\Gate::forUser($currentUser)->allows('view', $thread)) {
            return redirect()->route('threads.index')
                ->withErrors(['r18' => \App\Services\LanguageService::trans('r18_thread_adult_only_view', $lang)]);
        }
        
        // セッションに了承フラグを保存（非ログイン時でも可能）
        session(['acknowledged_thread_' . $threadId => true]);

        return redirect()->route('threads.show', $thread)
            ->with('success', \App\Services\LanguageService::trans('acknowledgment_success', $lang));
    }

    /**
     * レスポンスの制限を了承する
     *
     * @param  int  $threadId
     * @param  int  $responseId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function acknowledgeResponse($threadId, $responseId)
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        
        $thread = Thread::findOrFail($threadId);
        
        // IDOR防止: R18スレッドの閲覧権限をチェック（18歳未満のユーザーは了承不可）
        $currentUser = auth()->user();
        if (!\Illuminate\Support\Facades\Gate::forUser($currentUser)->allows('view', $thread)) {
            return redirect()->route('threads.index')
                ->withErrors(['r18' => \App\Services\LanguageService::trans('r18_thread_adult_only_view', $lang)]);
        }
        
        $response = Response::findOrFail($responseId);
        
        if (!Auth::check()) {
            // 非ログイン時は、ログイン後に元のページに戻るようにURLを保存
            session(['intended_url' => route('threads.show', $thread) . '#response-' . $responseId]);
            // レスポンスIDをセッションに保存（ログイン後に承認フラグを設定するため）
            session(['pending_acknowledge_response_' . $responseId => true]);
            return redirect()->route('login')->with('message', \App\Services\LanguageService::trans('login_required', $lang));
        }
        
        // セッションに了承フラグを保存
        session(['acknowledged_response_' . $responseId => true]);

        return redirect()->route('threads.show', $thread)
            ->with('success', \App\Services\LanguageService::trans('acknowledgment_success', $lang));
    }
}

