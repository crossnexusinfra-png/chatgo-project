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

## ユーザー入力文字列の対策一覧

### 通報理由（自由入力・description）

**実装場所**: `app/Http/Controllers/ReportController.php`, 管理画面ビュー

- **バリデーション**: `nullable|string|max:300`
- **保存時**: `strip_tags()` でHTMLタグ除去のうえ、`mb_substr(..., 0, 300)` で長さを保証
- **表示**: 管理画面で `{{ $r->description }}`（Blade の自動エスケープ）
- **フォーム**: `maxlength="300"` を付与

### 改善要望（message）

**実装場所**: `app/Http/Controllers/SuggestionController.php`, 管理画面・トップページフォーム

- **バリデーション**: `required|string|max:1000`
- **保存時**: `strip_tags()` でHTMLタグ除去のうえ、`mb_substr(..., 0, 1000)` で長さを保証
- **表示**: 管理画面で `{{ $s->message }}`（Blade の自動エスケープ）
- **フォーム**: `maxlength="1000"` を付与

### お知らせへの返信（body）

**実装場所**: `app/Http/Controllers/NotificationsController.php`, `public/js/notifications-index.js`

- **バリデーション**: `required|string|max:2000`
- **保存時**: `strip_tags()` でHTMLタグ除去のうえ、`mb_substr(..., 0, 2000)` で長さを保証
- **表示**: 通知一覧では `renderMessageBodySafe()`（HTMLエスケープ＋改行→`<br>`＋URLリンク化）で `innerHTML` に安全に代入。管理画面では `{{ $m->body }}`（Blade の自動エスケープ）
- **フォーム**: `maxlength="2000"` を付与

---

## スレッド（ルーム名）・リプライとの対策項目の差

| 対策項目 | スレッド（タイトル・本文） | リプライ（レス本文） | 通報理由（自由入力） | 改善要望 | お知らせ返信 |
|----------|---------------------------|------------------------|------------------------|----------|--------------|
| **バリデーション（長さ・必須）** | ○ title max:50, body max:1000 | ○ body nullable（ファイル時は不要） | ○ description nullable, max:300 | ○ message required, max:1000 | ○ body required, max:2000 |
| **保存時の strip_tags** | なし（表示でエスケープ） | なし（表示でエスケープ） | ○ 実装済み | ○ 実装済み | ○ 実装済み |
| **表示時のエスケープ** | ○ `{{ }}` / `linkify_urls(e())` | ○ `linkify_urls(e())` | ○ `{{ }}`（管理画面） | ○ `{{ }}`（管理画面） | ○ JS: `renderMessageBodySafe()` / 管理: `{{ }}` |
| **URL チェック（Safe Browsing）** | ○ 本文中のURLをチェック | ○ 本文中のURLをチェック | × 不要（補足説明用） | × 不要 | × 不要 |
| **スパム判定** | ○ SpamDetectionService | ○ SpamDetectionService | × 不要 | × 不要 | × 不要 |
| **メディア添付** | ○ 画像（検証・リネーム等） | ○ メディア（検証・リネーム等） | × なし | × なし | × なし |
| **公開範囲** | 一覧・詳細で誰でも閲覧 | スレッド閲覧者に表示 | 管理画面のみ | 管理画面のみ | 管理画面＋本人の通知 |

### 保存時の strip_tags について

- **スレッド・リプライで「なし」にしている理由**
  - 本文は**表示時に必ずエスケープ**（`linkify_urls(e($text))`）しており、DBには生のテキストのまま保存している。
  - 保存時に `strip_tags` しないことで、「&lt;」などの文字を意図して書いた投稿が、保存時点で別の形に変わってしまうことを避けられる（例: 「5&lt;10」が「510」にならない）。
  - 表示箇所が複数（スレッド一覧・詳細・検索・翻訳など）あり、いずれも「エスケープしてから表示」という一貫した方針にしているため、**表示層での対策だけで十分**としている。

- **通報理由・改善要望・お知らせ返信で「実装済み」にしている理由**
  - これらは**管理画面や本人の通知**でしか表示されず、表示箇所が限定的。
  - **防御の多重化**のため、保存時にも `strip_tags` をかけておく。万一表示側のエスケープが漏れても、DBにHTMLタグが残らないようにする。
  - リンク化などのリッチ表示が不要なプレーンテキストとして扱う前提のため、保存時点でタグをはぎ取っても運用上の不都合が少ない。

### 表示時のエスケープについて

| 方式 | 役割 | 使っている箇所 |
|------|------|----------------|
| **`{{ $var }}`** | Blade のデフォルト出力。HTML特殊文字（`<`, `>`, `"`, `'`, `&`）をエスケープし、**タグやスクリプトを実行されないようにする**。管理画面の通報理由・改善要望・お知らせ本文も同じく `{{ }}` で出力している。 | スレッドタイトル、通報理由・改善要望・お知らせの管理画面など、**単なるテキスト表示**で十分な箇所。 |
| **`linkify_urls(e($text))`** | まず `e($text)` でHTMLエスケープし、そのうえで改行→`<br>`、URL→`<a>` に変換。**エスケープ済みの文字列だけを加工**するので、`<script>` 等は実行されず文字として表示される。 | スレッド本文・**リプライ本文**（公開されるテキストで、改行とURLリンクだけ許可したい箇所）。 |
| **`renderMessageBodySafe()`（JS）** | クライアント側で、本文を **HTMLエスケープ** したあと、改行→`<br>`・URL→`<a>` に変換してから `innerHTML` に代入。サーバーから受け取った生文字列をそのまま `innerHTML` に入れないことで **XSS を防ぐ**。 | お知らせ本文を **JavaScript で開閉表示している**通知一覧ページ。サーバー描画ではなく JS で DOM を更新するため、PHP の `e()` が使えず、同じ考え方を JS で実装している。 |

**リプライに `{{ }}` を直接使っていない理由**

- リプライ本文を `{{ $response->body }}` だけにすると**改行が反映されず**（`\n` がそのまま出る）、**URLもリンクにならない**。
- そのため、「エスケープ」は **`linkify_urls()` の内部で `e($text)` として行い**、改行とURLだけを許可されたタグ（`<br>`, `<a>`）に変換している。ビューでは `{!! linkify_urls($response->display_body ?? $response->body) !!}` と **`{!! !!}`（未エスケープ出力）** を使っているが、**渡している文字列はすでに `e()` 済み＋許可タグのみ**なので、実質的に `{{ }}` と同じ安全性になっている。
- まとめると、リプライも「表示時にエスケープ」はしており、**単純な `{{ }}` ではなく「エスケープ＋改行・URLだけリッチ表示」の `linkify_urls(e())` を採用している**、という違いだけ。

**まとめ**

- **保存時**: スレッド・リプライは「生で保存し、表示でだけエスケープ」。通報・改善要望・お知らせ返信は「保存時にも `strip_tags` でタグを落とし、表示時エスケープと二重で守る」。
- **表示時**: サーバー描画なら `{{ }}` または `linkify_urls(e())`、JS で DOM を更新する箇所だけ `renderMessageBodySafe()` のように「必ずエスケープしてから innerHTML に渡す」ようにしている。

**差の要点**

- **スレッド・リプライ**: 公開コンテンツのため、URLチェック・スパム判定・メディア検証を実施。表示は `linkify_urls(e())` でエスケープ＋URLリンク化。
- **通報理由・改善要望・お知らせ返信**: 管理向けまたは本人向けのため、URLチェック・スパム判定・メディアは行わない。代わりに保存時の `strip_tags` と表示時の確実なエスケープ（Blade の `{{ }}` または JS の `renderMessageBodySafe`）で XSS 等を防止している。
