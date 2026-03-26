<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
        // 毎リクエスト更新は重いため、一定間隔で1回のみ実行する
        try {
            if (Cache::add('reports_reset_expired_out_counts_running', 1, now()->addMinutes(10))) {
                \App\Models\Report::resetExpiredOutCounts();
            }
        } catch (\Throwable $e) {
            // ここで失敗しても閲覧継続を優先
        }

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
            $routeName = $request->route()?->getName();
            // 永久凍結の場合、閲覧は許可しつつ、ログアウト以外の非GET操作を禁止
            if ($user->is_permanently_banned) {
                $allowedNonGetRoutes = [
                    'logout',
                    // 閲覧専用の継続のため、警告/R18確認の了承は許可
                    'threads.acknowledge',
                    'responses.acknowledge',
                ];
                $isAllowedNonGet = in_array($routeName, $allowedNonGetRoutes, true) || $request->is('logout');
                if (!$request->isMethod('GET') && !$isAllowedNonGet) {
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
                    $allowedNonGetRoutes = [
                        'logout',
                        // 閲覧専用の継続のため、警告/R18確認の了承は許可
                        'threads.acknowledge',
                        'responses.acknowledge',
                        // 一時凍結中は通知からのコイン受け取りを許可
                        'notifications.receive-coin',
                    ];

                    // 許可されたルート以外は拒否
                    if (!$request->isMethod('GET') && !in_array($routeName, $allowedNonGetRoutes, true)) {
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
