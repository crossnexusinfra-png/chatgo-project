<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        // ログインユーザーのみチェック
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // 1年経過した通報のアウト数をリセット
        \App\Models\Report::resetExpiredOutCounts();

        // 凍結状態をチェック
        if ($user->isFrozen()) {
            // 永久凍結の場合、ログアウト以外すべて禁止
            if ($user->is_permanently_banned) {
                $routeName = $request->route()?->getName();
                
                // ログアウトのみ許可（GETとPOSTの両方）
                if ($routeName !== 'logout' && !$request->is('logout')) {
                    $lang = \App\Services\LanguageService::getCurrentLanguage();
                    $message = \App\Services\LanguageService::trans('user_permanently_banned_message', $lang);
                    
                    // GETリクエストの場合はログアウトページにリダイレクト
                    if ($request->isMethod('GET')) {
                        return redirect()->route('logout')->withErrors(['frozen' => $message]);
                    }
                    
                    return back()->withErrors(['frozen' => $message]);
                }
            } else {
                // 一時凍結の場合
                // 凍結期間が過ぎている場合は解除
                if ($user->frozen_until && $user->frozen_until->isPast()) {
                    $user->frozen_until = null;
                    $user->save();
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
