<?php

namespace App\Services;

/**
 * 外部API実行時に「どのAPIが呼ばれたか」をセッションに記録し、画面でアラート表示するためのサービス。
 * EXTERNAL_API_DEBUG_ALERT=true のときのみ記録する。
 */
class ExternalApiAlertService
{
    private const SESSION_KEY = 'external_apis_called';

    /**
     * 指定した外部API名を「呼び出し済み」として記録する。
     * 同一リクエスト内で複数APIが呼ばれた場合は配列で保持し、次のレスポンスでまとめてアラート表示する。
     *
     * @param string $apiName 表示用のAPI名（例: 翻訳API (OpenAI), Safe Browsing API (Google)）
     */
    public static function record(string $apiName): void
    {
        if (!config('app.external_api_debug_alert', false)) {
            return;
        }

        $apis = session()->get(self::SESSION_KEY, []);
        if (!is_array($apis)) {
            $apis = [];
        }
        $apis[] = $apiName;
        session()->flash(self::SESSION_KEY, array_values(array_unique($apis)));
    }

    /**
     * 記録された外部API名の配列を取得（表示用）。
     *
     * @return array<string>
     */
    public static function getRecorded(): array
    {
        $apis = session()->get(self::SESSION_KEY, []);
        return is_array($apis) ? $apis : [];
    }
}
