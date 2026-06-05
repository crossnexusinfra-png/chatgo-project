<?php

namespace App\Services;

use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailVerificationService
{
    /**
     * メール認証コードを送信する。失敗時はログに記録する。
     */
    public static function sendVerificationCode(string $email, string $code, string $lang, string $context = ''): void
    {
        try {
            Mail::to($email)->send(new VerificationCodeMail($code, $lang));
            Log::info('メール認証コード送信成功', [
                'email' => $email,
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
            Log::warning('メール認証コード送信失敗', [
                'email' => $email,
                'context' => $context,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info("メール認証コード: {$code} (メール: {$email})" . ($context !== '' ? " [{$context}]" : ''));
    }
}
