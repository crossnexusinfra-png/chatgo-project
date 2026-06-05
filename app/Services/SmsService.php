<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * SMS認証コードを送信する。Twilio 未設定時はログ出力のみ。
     */
    public static function sendVerificationCode(string $phone, string $code, string $context = ''): void
    {
        $to = self::toE164($phone);
        $message = self::buildVerificationMessage($code);

        if (self::isTwilioConfigured()) {
            if (self::sendViaTwilio($to, $message)) {
                Log::info('SMS認証コード送信成功', [
                    'phone' => $to,
                    'context' => $context,
                ]);
                return;
            }

            Log::warning('SMS認証コード送信失敗（Twilio）', [
                'phone' => $to,
                'context' => $context,
            ]);
        }

        Log::info("SMS認証コード: {$code} (電話番号: {$phone})" . ($context !== '' ? " [{$context}]" : ''));
    }

    private static function isTwilioConfigured(): bool
    {
        return (bool) config('services.sms.enabled')
            && config('services.sms.twilio.sid')
            && config('services.sms.twilio.token')
            && config('services.sms.twilio.from');
    }

    private static function sendViaTwilio(string $to, string $body): bool
    {
        try {
            $sid = (string) config('services.sms.twilio.sid');
            $response = Http::withBasicAuth($sid, (string) config('services.sms.twilio.token'))
                ->asForm()
                ->timeout(10)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $to,
                    'From' => (string) config('services.sms.twilio.from'),
                    'Body' => $body,
                ]);

            if (!$response->successful()) {
                Log::warning('Twilio SMS API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Twilio SMS exception', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private static function toE164(string $phone): string
    {
        if (str_starts_with($phone, '+')) {
            return '+' . preg_replace('/\D/', '', substr($phone, 1));
        }

        return preg_replace('/\D/', '', $phone);
    }

    private static function buildVerificationMessage(string $code): string
    {
        $appName = config('app.name', 'Chatgo');

        return "{$appName} 認証コード: {$code}（5分間有効）";
    }
}
