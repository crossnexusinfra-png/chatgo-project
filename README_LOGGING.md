# ログ実装ガイド

このプロジェクトでは、以下のログシステムを実装しています。

## 1. Laravel Telescope（開発環境のみ）

### 概要
開発環境でのデバッグと情報可視化のためのツールです。

### セットアップ

```bash
# Telescopeをインストール
composer require laravel/telescope --dev

# マイグレーションを実行
php artisan telescope:install
php artisan migrate

# 設定を公開（既にconfig/telescope.phpが作成済み）
# php artisan vendor:publish --tag=telescope-config
```

### 使用方法

1. `.env`ファイルで有効化：
```env
TELESCOPE_ENABLED=true
APP_ENV=local
```

2. ブラウザでアクセス：
```
http://your-app.test/telescope
```

3. アクセス制御：
- 開発環境（`local`, `development`）でのみアクセス可能
- `app/Providers/TelescopeServiceProvider.php`で制御

### 機能
- リクエスト/レスポンスの記録
- クエリログ
- 例外の記録
- ログの可視化
- メール送信の記録
- ジョブの記録
- イベントの記録

## 2. Monolog（異常ログ取得）

### 概要
Monologを使用した異常ログの取得と記録システムです。

### 設定

`config/logging.php`に以下のチャンネルが追加されています：

- `error_file`: エラーレベル以上のログ（30日間保持）
- `warning_file`: 警告レベルのログ（14日間保持）

### 使用方法

```php
use App\Services\LogService;

// エラーログを記録
LogService::logError('エラーが発生しました', [
    'user_id' => 123,
    'action' => 'update_profile',
]);

// 警告ログを記録
LogService::logWarning('警告が発生しました', [
    'user_id' => 123,
]);

// 例外をログに記録
try {
    // 何らかの処理
} catch (\Exception $e) {
    LogService::logException($e, [
        'additional_context' => 'value',
    ]);
}

```

### ログファイルの場所

- `storage/logs/error-YYYY-MM-DD.log`: エラーログ（30日間保持、自動削除）
- `storage/logs/warning-YYYY-MM-DD.log`: 警告ログ（14日間保持、自動削除）

## 3. Cloudflare（本番環境のみ）

### 概要
本番環境でのアクセスログの保存と異常通知システムです。

### セットアップ

1. `.env`ファイルに設定を追加：

```env
# Cloudflare設定
CLOUDFLARE_ENABLED=true
CLOUDFLARE_API_TOKEN=your_api_token
CLOUDFLARE_ZONE_ID=your_zone_id
CLOUDFLARE_LOG_RETENTION_DAYS=30

# アラート設定
CLOUDFLARE_ALERTS_ENABLED=true
CLOUDFLARE_ALERT_WEBHOOK_URL=https://your-webhook-url
CLOUDFLARE_ALERT_EMAIL=admin@example.com

# 分析設定
CLOUDFLARE_ANALYSIS_ENABLED=true
CLOUDFLARE_CHECK_INTERVAL=5
CLOUDFLARE_ERROR_THRESHOLD=10
```

2. Cloudflare APIトークンの取得：
   - CloudflareダッシュボードでAPIトークンを作成
   - 必要な権限：Zone Read, Logs Read

### 機能

- **アクセスログの保存**: すべてのリクエストを記録
- **異常検出**: 5分間で10件以上のエラーを検出
- **通知**: Webhookまたはメールで通知

### 使用方法

```php
use App\Services\CloudflareLogService;

// アクセスログを保存
CloudflareLogService::saveAccessLog([
    'method' => 'POST',
    'url' => '/api/users',
    'status_code' => 200,
]);

// 異常を検出して通知
CloudflareLogService::detectAnomaly('異常が検出されました', [
    'details' => '詳細情報',
]);
```

### 注意事項

- 本番環境（`production`）でのみ有効化されます
- Cloudflare APIトークンとZone IDが必要です
- 実際のCloudflare API連携は本番環境で設定を確認してから実装してください

## 4. ファイルログ（異常ログ保存）

### 概要
異常ログを専用ファイルに保存するシステムです。

### 設定

`config/logging.php`の`error_file`と`warning_file`チャンネルを使用します。

### ログファイル

- `storage/logs/error-YYYY-MM-DD.log`: エラーログ（30日間保持、自動削除）
- `storage/logs/warning-YYYY-MM-DD.log`: 警告ログ（14日間保持、自動削除）

### 自動記録

以下の場合に自動的に記録されます：

1. **例外発生時**: `bootstrap/app.php`の例外ハンドラーで自動記録
2. **エラーレスポンス**: 500番台のステータスコード
3. **手動記録**: `LogService`を使用

### 自動削除

`routes/console.php`でスケジューラーを設定し、古いログファイルを自動削除します：

- **エラーログ**: 30日以上古いファイルを毎日削除
- **警告ログ**: 14日以上古いファイルを毎日削除
- **Cloudflareアクセスログ**: 30日以上古いファイルを毎日削除

**注意**: スケジューラーを動作させるには、サーバーのcronジョブで以下を設定する必要があります：

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## 環境別設定

### 開発環境（local）

```env
APP_ENV=local
LOG_CHANNEL=stack
LOG_STACK=single
TELESCOPE_ENABLED=true
CLOUDFLARE_ENABLED=false
```

### 本番環境（production）

```env
APP_ENV=production
LOG_CHANNEL=stack
LOG_STACK=daily,error_file
TELESCOPE_ENABLED=false
CLOUDFLARE_ENABLED=true
```

## ログの確認方法

### コマンドライン

```bash
# 最新のエラーログを確認
tail -f storage/logs/error-$(date +%Y-%m-%d).log

# 最新の警告ログを確認
tail -f storage/logs/warning-$(date +%Y-%m-%d).log

# Cloudflareアクセスログを確認
tail -f storage/logs/cloudflare-access.log
```

### 管理画面

開発環境では、Laravel Telescopeを使用してログを可視化できます。

## トラブルシューティング

### Telescopeにアクセスできない

1. `.env`で`TELESCOPE_ENABLED=true`を確認
2. `APP_ENV=local`を確認
3. マイグレーションが実行されているか確認

### ログが記録されない

1. `storage/logs`ディレクトリの書き込み権限を確認
2. `.env`の`LOG_CHANNEL`設定を確認
3. ログレベルが適切か確認

### Cloudflareログが記録されない

1. `.env`で`CLOUDFLARE_ENABLED=true`を確認
2. `APP_ENV=production`を確認
3. APIトークンとZone IDが正しく設定されているか確認
