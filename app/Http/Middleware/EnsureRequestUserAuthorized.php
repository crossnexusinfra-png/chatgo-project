<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * リクエストボディに含まれるユーザー識別子が、認証ユーザーと一致するか検証する。
 * 送信しようとしているユーザーと送信すべきユーザーが一致しているか権限制御する。
 */
class EnsureRequestUserAuthorized
{
    /**
     * チェック対象のリクエストキーと、認証ユーザーの主キー名。
     * キーがリクエストに含まれ、かつ値が認証ユーザーと異なる場合は 403 を返す。
     *
     * @var array<string, string>  [ request_key => auth_attribute ]
     */
    protected $userKeys = [
        'user_id' => 'user_id',
        'from_user_id' => 'user_id',
    ];

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return $next($request);
        }

        $authUser = auth()->user();
        $authId = $authUser->user_id ?? $authUser->getAuthIdentifier();

        foreach ($this->userKeys as $requestKey => $authAttribute) {
            if (!$request->has($requestKey)) {
                continue;
            }

            $requestValue = $request->input($requestKey);
            if ($requestValue === null || $requestValue === '') {
                continue;
            }

            $expected = $authUser->{$authAttribute} ?? $authId;
            if ((string) $requestValue !== (string) $expected) {
                abort(403, __('request_user_mismatch'));
            }
        }

        return $next($request);
    }
}
