<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class CheckUserFrozen
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 管理画面は Basic 認証等で別経路のため凍結チェックをスキップ（凍結ユーザーが管理操作できなくなるのを防ぐ）
        $adminPrefix = trim((string) config('admin.prefix', 'admin'), '/');
        if ($adminPrefix === '') {
            $adminPrefix = 'admin';
        }
        if ($request->is($adminPrefix) || $request->is($adminPrefix.'/*')) {
            return $next($request);
        }

        // 画面用（閲覧専用UIの表示）
        View::share([
            'viewerAccountFrozen' => false,
            'viewerFrozenUiMessage' => '',
        ]);

        // ログインユーザーのみチェック
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        // セッション上のモデルが古い場合があるため DB の最新状態で凍結を判定する
        $user->refresh();

        // アウト数の時効（表示・凍結判定の整合用）
        \App\Models\Report::resetExpiredOutCounts();

        if ($user->isFrozen()) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            if ($user->is_permanently_banned) {
                $uiMessage = \App\Services\LanguageService::trans('user_permanently_banned_message', $lang);
            } elseif ($user->frozen_until && $user->frozen_until->isFuture()) {
                $uiMessage = \App\Services\LanguageService::trans('user_temporarily_frozen_message', $lang, [
                    'until' => $user->frozen_until->format('Y-m-d H:i'),
                ]);
            } else {
                $uiMessage = \App\Services\LanguageService::trans('user_frozen_message', $lang);
            }
            View::share([
                'viewerAccountFrozen' => true,
                'viewerFrozenUiMessage' => $uiMessage,
            ]);
        }

        // 凍結状態をチェック（refresh 後の属性で再判定）
        if ($user->isFrozen()) {
            // 永久凍結の場合、ログアウト以外すべて禁止
            if ($user->is_permanently_banned) {
                if (!$request->isMethod('GET')) {
                    $lang = \App\Services\LanguageService::getCurrentLanguage();
                    $message = \App\Services\LanguageService::trans('user_permanently_banned_message', $lang);

                    return back()->withErrors(['frozen' => $message]);
                }
            } else {
                // 一時凍結の場合
                // 凍結期間が過ぎている場合は解除
                if ($user->frozen_until && $user->frozen_until->isPast()) {
                    $user->frozen_until = null;
                    $user->save();
                    View::share([
                        'viewerAccountFrozen' => false,
                        'viewerFrozenUiMessage' => '',
                    ]);
                } else {
                    // 凍結中の場合、閲覧以外の操作を禁止
                    $allowedRoutes = [
                        'threads.index',
                        'threads.show',
                        'threads.search',
                        'threads.tag',
                        'threads.category',
                        'profile.show',
                        'logout',
                    ];

                    $routeName = $request->route()?->getName();

                    // 許可されたルート以外は拒否
                    if (!in_array($routeName, $allowedRoutes) && !$request->isMethod('GET')) {
                        $lang = \App\Services\LanguageService::getCurrentLanguage();
                        $message = \App\Services\LanguageService::trans('user_temporarily_frozen_message', $lang, [
                            'until' => $user->frozen_until->format('Y-m-d H:i')
                        ]);

                        return back()->withErrors(['frozen' => $message]);
                    }
                }
            }
        }

        return $next($request);
    }
}
