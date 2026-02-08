# セキュリティ実装詳細

## 実装済みセキュリティ対策

### 1. ドメインホワイトリスト

**実装場所**: `app/Services/SecureHttpClientService.php`

- 外部APIへのアクセスを許可されたドメインのみに制限
- デフォルトで許可されるドメイン:
  - `safebrowsing.googleapis.com` (Google Safe Browsing API)
  - `api.veriphone.io` (Veriphone API)
  - ※言語判定は Cloudflare の CF-IPCountry ヘッダを使用するため IP Geolocation API は使用しない
- 追加のドメインは `config/security.php` の `allowed_domains` で設定可能

**使用方法**:
```php
use App\Services\SecureHttpClientService;

$response = SecureHttpClientService::get($url);
$response = SecureHttpClientService::post($url, $data);
```

### 2. DNS→IP解決後の内部IP遮断

**実装場所**: `app/Services/SecureHttpClientService.php`

- DNS解決後にIPアドレスを取得
- プライベートIPアドレス（内部IP）へのアクセスを遮断
- 遮断されるIP範囲:
  - IPv4: `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `127.0.0.0/8`, `169.254.0.0/16`
  - IPv6: `::1/128`, `fc00::/7`, `fe80::/10`
- DNS解決結果は5分間キャッシュ

### 3. HTTPクライアント制限

**実装場所**: `app/Services/SecureHttpClientService.php`

- **タイムアウト**: デフォルト10秒（設定可能）
- **リダイレクト制限**: デフォルトでリダイレクト禁止（`max_redirects: 0`）
- **最大レスポンスサイズ**: デフォルト10MB（設定可能）
- 設定は `config/security.php` の `http_client` セクションで変更可能

**環境変数**:
```env
HTTP_CLIENT_TIMEOUT=10
HTTP_CLIENT_MAX_REDIRECTS=0
HTTP_CLIENT_MAX_RESPONSE_SIZE=10485760
```

### 4. ファイルパスの正規化

**実装場所**: `app/Services/SecureFilePathService.php`

- `realpath()`を使用して実態パスを取得（シンボリックリンクを解決）
- 相対パス（`../`など）を信用せず、ルートディレクトリを基準に解決
- ルートディレクトリ外へのアクセスを禁止
- 許可されたルートディレクトリ:
  - `storage`: `storage/app/public`
  - `public`: `public`

**使用方法**:
```php
use App\Services\SecureFilePathService;

// パスを正規化（ルートディレクトリ外へのアクセスを防止）
$normalizedPath = SecureFilePathService::normalizePath($userInputPath, 'storage');
if ($normalizedPath === null) {
    // 無効なパス
}

// 安全なファイル名を生成
$safeFilename = SecureFilePathService::generateSafeFilename($userInput, $extension);
```

### 5. メディアファイル名などユーザー入力をそのままパスに使わない

**実装場所**: `app/Http/Controllers/ResponseController.php`, `app/Http/Controllers/ThreadController.php`

- ユーザー入力のファイル名を直接使用せず、SHA256ハッシュでリネーム
- 拡張子はMIMEタイプから取得（ユーザー入力に依存しない）
- 実装例:
```php
$hashedFilename = hash('sha256', time() . $file->getClientOriginalName());
$extension = $this->getExtensionFromMimeType($file->getMimeType(), $mediaType);
$filename = $hashedFilename . '.' . $extension;
```

### 6. OSコマンド実行関数をコード内で使用しない+ユーザー入力で受け付けない

**確認済み**: `app/Services/MediaFileValidationService.php`, `app/Services/MediaFileProcessingService.php`

- `Process`クラスを使用してOSコマンドを実行
- コマンド引数は固定値（ユーザー入力を使用しない）
- ファイルパスは`getRealPath()`で取得（アップロードされたファイルの一時パス）
- 実行されるコマンド:
  - `ffprobe`: メディアファイルの検証
  - `ffmpeg`: メタデータの削除
  - `clamscan`/`clamdscan`: ウイルススキャン

**安全な実装例**:
```php
$filePath = $file->getRealPath(); // ユーザー入力ではない
$process = new Process([
    'ffprobe', // 固定値
    '-v', 'error', // 固定値
    '-show_entries', 'format=format_name,size', // 固定値
    '-of', 'json', // 固定値
    $filePath, // getRealPath()で取得（安全）
]);
```

### 7. ユーザー入力を命令文に埋め込まない

**確認済み**: すべてのサービスファイル

- SQLクエリ: Eloquent ORMを使用（パラメータ化クエリ）
- OSコマンド: 固定値のみ使用、ユーザー入力は使用しない
- HTTPリクエスト: URLパラメータは適切にエンコード

## 既存コードの修正

以下のサービスで`SecureHttpClientService`を使用するように修正しました:

1. **SafeBrowsingService**: Google Safe Browsing APIへのアクセス
2. **VeriphoneService**: Veriphone APIへのアクセス
3. **LanguageService**: 言語判定は Cloudflare の CF-IPCountry ヘッダ（国コード）のみを使用（IP・外部APIは使用しない）
4. **CloudflareLogService**: Webhookへの通知

## 設定ファイル

### config/security.php

```php
return [
    'allowed_domains' => [
        // 追加の許可ドメイン
    ],
    'http_client' => [
        'timeout' => env('HTTP_CLIENT_TIMEOUT', 10),
        'max_redirects' => env('HTTP_CLIENT_MAX_REDIRECTS', 0),
        'max_response_size' => env('HTTP_CLIENT_MAX_RESPONSE_SIZE', 10485760),
    ],
    'file_path' => [
        'allowed_roots' => [
            'storage' => 'storage/app/public',
            'public' => 'public',
        ],
    ],
];
```

## テスト推奨事項

1. ドメインホワイトリスト: 許可されていないドメインへのアクセスが拒否されることを確認
2. 内部IP遮断: プライベートIPアドレスへのアクセスが遮断されることを確認
3. リダイレクト制限: リダイレクトが適切に制限されることを確認
4. レスポンスサイズ制限: 大きなレスポンスが拒否されることを確認
5. ファイルパス正規化: ディレクトリトラバーサル攻撃が防止されることを確認

## 注意事項

- 新しい外部APIを使用する場合は、`config/security.php`の`allowed_domains`にドメインを追加する必要があります
- リダイレクトが必要な場合は、`HTTP_CLIENT_MAX_REDIRECTS`環境変数で制限回数を設定できます
- ファイルパス処理でユーザー入力を使用する場合は、必ず`SecureFilePathService`を使用してください
