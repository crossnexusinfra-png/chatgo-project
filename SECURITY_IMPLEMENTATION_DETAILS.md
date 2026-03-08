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

---

## CSRF・XSS のテスト方法

### CSRF テスト

**自動テスト（PHPUnit Feature）**

- `tests/Feature/Security/CsrfProtectionTest.php` を参照。
- POST 系ルートに CSRF トークンなしでリクエストすると 419 または 302（ログインリダイレクト等）になることを確認する。

**手動確認（ウェブサイト上での具体的な操作）**

次のいずれかの方法で「CSRF トークンなしで POST する」状態を作り、送信すると **419 エラー** または **ログイン画面などへリダイレクト** になることを確認します。

**方法A: ブラウザの開発者ツールでフォームのトークンを削除する**

1. **ログイン**  
   - ログイン画面（`/login`）を開く。  
   - F12 で開発者ツールを開き、**Elements（要素）** タブで `<form>` を選択する。  
   - フォーム内の `<input type="hidden" name="_token" value="...">` を探し、この行を**削除**する（または `value=""` に変更する）。  
   - ユーザー名・パスワードを入力して「ログイン」ボタンを押す。  
   - **期待**: 419 のエラーページが表示される、またはログイン画面に戻り「CSRF token mismatch」などのメッセージが出る。

2. **スレッド作成**  
   - トップページでスレッド作成モーダルを開く。  
   - 開発者ツールでそのフォーム内の `_token` の hidden 入力欄を削除または空にする。  
   - タイトル・本文を入力して送信する。  
   - **期待**: 419 またはエラー表示／リダイレクトで投稿が受理されない。

3. **レス投稿**  
   - スレッド詳細ページでレス投稿フォームを表示する。  
   - 同様にフォーム内の `_token` を削除して送信する。  
   - **期待**: 419 またはリダイレクトで投稿されない。

**方法B: 開発者ツールのコンソールで fetch を送る**

1. 任意のページ（例: トップ）で F12 → **Console** を開く。  
2. 次のように **Cookie や CSRF トークンをつけずに** POST する（URL は実際のサイトに合わせて変更）:

   ```javascript
   fetch('/login', {
     method: 'POST',
     headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'text/html' },
     body: 'username=test&password=test',
     credentials: 'omit'  // Cookie を送らない
   }).then(r => console.log(r.status, r.url));
   ```

3. **期待**: `419` が返る（または 302 でログイン画面などに飛ぶ）。  
4. 同様に `fetch('/threads', { method: 'POST', ... })` などでもトークンなしで送ると 419 になることを確認する。

**方法C: curl でトークンなし POST を送る**

```bash
curl -X POST https://あなたのサイト/login \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "username=test&password=test" -i
```

レスポンスの HTTP ステータスが **419** または **302** であれば CSRF 検証が効いている。

### XSS テスト

**手動確認**

1. **Blade 出力**: スレッドタイトル・本文、リプライ本文に `<script>alert(1)</script>` や `<img src=x onerror=alert(1)>` を投稿し、一覧・詳細で「タグがそのまま文字として表示され、スクリプトが実行されない」ことを確認。
2. **CSP**: 開発者ツールのコンソールでインライン script がブロックされることを確認（nonce のない `<script>` は CSP でブロックされる）。
3. **通知本文（JS 表示）**: お知らせ返信に `<script>alert(1)</script>` を入れた場合、通知一覧でエスケープされて表示され、実行されないことを確認。

**自動テスト（オプション）**

- 上記の「危険な文字列を送信 → レスポンス HTML にエスケープされた形で含まれる」ことを Feature テストで assert する（例: レスポンスに `&lt;script&gt;` が含まれるが `<script>` が含まれない）。

---

## Rate Limit・IP 単位制限・.env 本番分離

### クライアント IP の取得（Laravel 標準 TrustProxies + Cloudflare 対応）

- **方式**: Laravel 標準の **TrustProxies** と **`$request->ip()`** でクライアント IP を取得する。
- **Cloudflare 経由時**: `TrustCloudflareProxies` ミドルウェア（先頭で `prepend`）が **`CF-Connecting-IP`** を **`X-Forwarded-For`** に反映する。これにより TrustProxies の対象ヘッダとして扱われ、`$request->ip()` でプロキシではなくクライアント IP が返る。
- レート制限・例外ログ・`LanguageService`・`SafeBrowsingService`・`TranslationService` などはすべて **`$request->ip()` / `request()->ip()`** のみを使用する（CF-Connecting-IP の直接参照は行わない）。

### Rate Limit（実装済み）

| 名前 | 制限 | キー | 適用ルート・用途 |
|------|------|------|------------------|
| `api` | 60/分 | user_id | `/api` 配下の JSON 取得（fetch 用）全般 |
| | 100/分 | IP | 同上 |
| `search` | 20/分 | user_id, IP 各 | `GET /search`, `GET /api/search/more` 等検索系 |
| `verification_initial_sms` | 1/分 | IP | 初期登録時 SMS 認証コード再送信 |
| | 1/分 | phone | 同上（セッションの電話番号でキー） |
| `verification_initial_email` | 1/分 | IP | 初期登録時 メール認証コード再送信 |
| | 1/分 | email | 同上（セッションのメールでキー） |
| `verification_profile` | 1/分 | user_id | 電話番号・メアド変更時の認証コード再送信 |
| `post` | 10/分 | user_id | ルーム作成・リプライ送信・リプライ返信 |
| | 30/分 | IP | 同上 |
| `safebrowsing` | 20/分 | user_id | Google Safe Browsing API 呼び出し（サービス内で手動チェック） |
| `veriphone` | 5/分 | IP, user_id 各 | 登録・プロフィール電話検証（SMS 再送等で Veriphone を叩くルート） |
| `openai` | 10/分 | user_id | 翻訳 API（TranslationService 内で手動チェック、1 投稿 1 翻訳＋1 年保存前提） |
| `ad_api` | 5/分 | user_id | 広告 API（今後実装予定、`/coins/watch-ad` に付与済み） |
| `coins_send` | 3/分 | user_id | コイン送信（送信元 user_id 単位、1 フレンド/1 投稿あたりではない） |
| | 20/日 | user_id | 同上 |
| `reports` | 10/分 | user_id | 通報（送信元 user_id 単位） |
| `notice_reply` | 5/分 | user_id | お知らせ返信（スレッド/レス制限了承・通知への返信） |
| `suggestions` | 3/分 | user_id | 改善要望 |
| `login` | 20/分 | IP | ログイン試行（同一IPあたり） |
| | 5/分 | ログイン対象メール（user_id） | 同上（同一メールあたり。失敗回数・ロックは LoginFailureService で別管理） |

設定: `bootstrap/app.php` の `RateLimiter::for(...)`。適用: `routes/web.php` の `->middleware('throttle:xxx')`。コイン送信・通報などは「1 フレンド/1 投稿あたり」ではなく **送信元 user_id 単位** の制限。

**単位ごとの整理**

- **IP**: 上記のとおり、すべて **クライアント IP**（`CF-Connecting-IP` 優先）でキー。
- **user_id**: 認証済みは user_id、未認証は IP（または `ip:xxx`）でフォールバック。
- **user_id 単位の別制限（RateLimiter 外）**:
  - **スレッド作成**: 1 日あたり **2 件まで**（`ThreadController::store` 内で `todayThreadCount >= 2` をチェック）。
- **同一スレッド単位の制限（RateLimiter 外）**:
  - **1 スレッドあたりのレスポンス数上限**: `config('performance.thread.max_responses')` = **500 件**（`ResponseController::store` / `reply` と `Thread::isRestricted()`）。500 に達したスレッドには新規投稿不可（続きルーム要望のみ）。

必要に応じて `bootstrap/app.php` の `Limit::perMinute(n)` 等を変更し、`php artisan config:clear` を実行する。

### 重複実行防止（実装済み）

**実装場所**: `app/Services/DuplicateSubmissionLockService.php`、各コントローラー

- ユーザー操作（ルーム作成・リプライ送信・リプライ返信・通報・改善要望・コイン送信・プロフィール更新・お知らせ返信・広告視聴・ログイン報酬など）の**二重送信**を防止するため、Cache ロック（**5秒**）を使用。
- **UIの送信ボタン無効化**に加え、サーバー側で**DB保存前に**同一アクションの二重送信を弾く（ロック取得に失敗したリクエストは処理を行わず即拒否）。
- 同一ユーザー（認証時は `user_id`、未認証時はセッションID）が同一アクション（＋オプションでリソースID）を短時間に複数回送信した場合、2回目以降は **429** または バリデーションエラー（`duplicate_submission`）で拒否。
- ロックキー例: `submission_lock:thread.store:{user_id}`、`submission_lock:response.reply:{user_id}:{thread_id}:{response_id}`。
- 各コントローラーで `DuplicateSubmissionLockService::acquire()` によりロック取得、処理後に `finally` で `$lock->release()` を実行。

**対象アクション**: ルーム作成、リプライ送信、リプライ返信、通報送信、改善要望送信、コイン送信（フレンド宛・広告視聴・ログイン報酬）、プロフィール更新、お知らせ返信・コイン受け取り・R18承認/拒否。

### 送信者一致の権限制御（実装済み）

**実装場所**: `app/Http/Middleware/EnsureRequestUserAuthorized.php`、ルート `request.user` ミドルウェア

- **送信しようとしているユーザーと送信すべきユーザーが一致しているか**を検証。リクエストボディに `user_id` または `from_user_id` が含まれる場合、その値が認証ユーザーの `user_id` と一致しないと **403** で拒否（なりすまし・パラメータ改ざんの防止）。
- 未認証時はチェックを行わず通過。
- 適用ルート: ルーム作成、リプライ送信・返信、通報、改善要望、プロフィール更新、フレンドへのコイン送信（`request.user` ミドルウェアを付与）。

**言語キー**: `request_user_mismatch`（権限なしメッセージ）、`duplicate_submission`（重複送信メッセージ）を `resources/lang/ja.php` および `en.php` に追加済み。

### ログイン失敗時制限（user_id＝メールアドレス単位・実装済み）

- **20 req/min/IP** と **5 req/min/ログイン対象メール**: `throttle:login` で制限。クライアント IP は TrustProxies + TrustCloudflareProxies により `$request->ip()` で取得。
- **失敗回数に応じた措置**（`LoginFailureService` + `AuthController::login`）:
  - **5回失敗**: ログイン画面に CAPTCHA 用エリア + パスワード初期化リンクを表示。
  - **10回失敗**: 10分間ロック + 異常ログインメール送信（IP / Country / Time を記載）。
  - **20回失敗**: 30分間ロック + 異常ログインメール送信（10回で送信済みの場合は20回で再送）。
  - **30回失敗**: 12時間ロック。
  - **50回失敗**: ログイン停止（パスワード初期化完了までログイン不可）。
- **異常ログインメール**: 件名「Abnormal login attempts detected」。本文に IP・Country・Time（`CF-IPCountry` を使用、未設定時は「—」）。
- **パスワード初期化フロー**: 電話番号とメールアドレスを正しく入力 → SMS + メールで認証コード送信 → 認証完了でログイン停止解除 → 強制パスワード変更画面で新パスワード設定 → ログイン可能。ルート: `GET/POST /login/password-reset`, `GET/POST /login/password-reset/verify`, `GET/POST /login/password-reset/change`。
- **CAPTCHA**: 5回失敗以降はログイン画面に `#captcha-container` を表示。reCAPTCHA 等を組み込む場合はここに配置する。

### IP 単位制限（実装済み）

- 上記のとおり、レート制限はすべて **クライアント IP**（Cloudflare の場合は `CF-Connecting-IP`）でキー。
- 管理画面の「特定 IP のみ許可」のようなホワイトリストは未実装。必要ならミドルウェアでクライアント IP を検査する実装を追加する。

---

## IP ホワイトリスト・異常 IP 制限・国制限

### IP ホワイトリスト（アクセス元の許可リスト）

- **未実装**。本アプリでは「この IP からのアクセスのみ許可する」といった**受信側の IP ホワイトリスト**は行っていない。
- なお **送信側**（アプリから外部 API へ出す際）の **ドメインホワイトリスト** は実装済み（`config/security.php` の `allowed_domains` と `SecureHttpClientService`）。許可ドメイン以外への HTTP アクセスは行わない。これは「アクセスしてよい相手のドメイン」の制限であり、**アクセスしてよいクライアント IP** の制限とは別物。

### 異常 IP 制限（ブラックリスト・不正検知）

- **未実装**。特定 IP のブロック、失敗回数に応じた一時ブロック（fail2ban 的）、異常トラフィック検知による IP 制限などは行っていない。
- IP は **レート制限のキー**（login / verification は IP、post / api は user_id または IP）および **ログ記録**（管理画面アクセス、Cloudflare ログなど）にのみ使用している。

### 国制限（国単位のアクセス許可・拒否）

- **未実装**。国コードによるアクセス許可／拒否（例: 特定国のみ許可、または特定国を拒否）は行っていない。
- **CF-IPCountry**（Cloudflare の国コードヘッダ）は **言語の自動切り替え**（`LanguageService`）にのみ利用しており、アクセス可否の判定には使っていない。

---

## Cloudflare の WAF/制限ルールと URI パスの対応

管理者画面の IP 制限・API アクセス制限・検索エンドポイント制限を Cloudflare で行う場合の **URI パス（contains）** の目安。

### ADMIN_PREFIX と管理者 URL

**はい。`ADMIN_PREFIX` を変えると、管理者ページ全体の URL が変わります。**

- 管理者ルートは `routes/admin.php` で **1 つの prefix** の下にまとまっています（`Route::prefix($prefix)->...`）。
- `$prefix` は `config('admin.prefix')` = **`.env` の `ADMIN_PREFIX`**（未設定時は `admin`）です。
- 例: `ADMIN_PREFIX=admin` → すべて `/admin`, `/admin/reports`, `/admin/logs` など。`ADMIN_PREFIX=manage` にすると → すべて `/manage`, `/manage/reports`, `/manage/logs` などに変わります。
- Cloudflare で「管理者だけ IP 制限」するときは、**実際に使っているプレフィックス** に合わせて「URI パス contains /admin」または「contains /manage」のように設定してください。

### 制限ごとの URI 条件の目安

| 制限の種類 | 条件例（URI パス） | 備考 |
|------------|-------------------|------|
| **管理者画面 IP 制限** | `contains /admin`（または `contains /{ADMIN_PREFIX の値}`） | 上記のとおり。`ADMIN_PREFIX` に合わせる。 |
| **API アクセス制限** | **`contains /api`** | データ取得系 API はすべて **`/api`** プレフィックスに集約済み。 |
| **検索エンドポイント制限** | `contains /search` | 検索トップは `GET /search`（HTML）。続き読みは **`GET /api/search/more`**。両方カバーするなら **`contains /search`** で可。 |

### 本実装における「API」の定義

このプロジェクトでは、**API** を次のように定義しています。

- **URL**: すべて **`/api` プレフィックス配下** のエンドポイント。
- **役割**: 主に **データの取得**（GET）で、**JSON を返す**もの。フロントの fetch/XHR から呼ばれ、ページ全体の HTML ではなく部分データ（一覧の続き・検索結果・レス一覧・残高など）を返す。
- **含まないもの**:
  - 通常のページ表示（HTML を返す GET。例: `/`, `/search`, `/threads/{id}`）は API には含めない。
  - フォーム送信（POST）で画面遷移するようなエンドポイント（例: ログイン、スレッド作成、レス投稿）は `/api` には含めず、従来どおりのパスのまま。

**まとめ**: 「**同一オリジンのフロントから fetch 等で呼び、JSON でデータを返す GET エンドポイント**」を API とみなし、それらを `/api` 以下に集約して、Cloudflare の「URI パス contains /api」で一括してレート制限・アクセス制限の対象にしている。

### 「重い・セキュリティ推奨」はすべて /api か？（いいえ）

**いいえ。** 処理が重くなりがちなものや Rate Limit 等のセキュリティ対策を推奨する箇所の**すべて**が `/api` に分類されているわけではありません。

| 種別 | 例 | /api 配下か | レート制限など |
|------|-----|-------------|----------------|
| **GET・JSON 返却**（データ取得） | 検索続き、レス一覧、スレッド続き、残高 等 | ✅ はい | `throttle:api`（60/分）。Cloudflare「contains /api」で一括可能。 |
| **POST・ログイン** | `POST /login` | ❌ いいえ | `throttle:login`（5/分・IP）。Cloudflare で制限するなら「contains /login」等で別ルール。 |
| **POST・認証コード再送** | `POST /register/sms-resend`, `.../email-resend`, `.../profile/...-resend` | ❌ いいえ | `throttle:verification`（1/分・IP）。別ルールが必要。 |
| **POST・投稿系** | `POST /threads`, `POST /threads/.../responses`, `.../reply` | ❌ いいえ | `throttle:post`（10/分）。別ルールが必要。 |
| **GET・HTML 返却**（ページ表示） | `/search`, `/threads/{id}`, `/tag/{tag}`, `/category/...` | ❌ いいえ | アプリ側の Rate Limit は特になし。重い場合は Cloudflare で「contains /search」等を別途制限。 |

- **/api に集約しているのは「GET で JSON を返すデータ取得系」だけ**です。ログイン・認証コード再送・投稿といった **POST 系**は従来どおりのパス（`/login`, `/register/...`, `/threads` 等）のままで、Laravel の `throttle:login` / `throttle:verification` / `throttle:post` で個別に制限しています。
- Cloudflare で「**URI パス contains /api**」とすれば **/api 配下の GET のみ**が一括対象になります。ログイン・投稿・認証コード再送を Cloudflare で制限したい場合は、**別のルール**（例: contains /login, contains /register, contains /threads など）を追加する必要があります。

---

### API 直叩き制限（実装済み: すべて /api 配下）

**データ取得系の API はすべて `/api` プレフィックスに集約しています。** Cloudflare で「URI パス contains /api」とすれば、以下が一括で対象になります。

| 種別 | URL 例 | ルート名 |
|------|--------|----------|
| 検索続き | `GET /api/search/more` | api.threads.search.more |
| タグ続き | `GET /api/tag/{tag}/more` | api.threads.tag.more |
| カテゴリ続き | `GET /api/category/{category}/more` | api.threads.category.more |
| スレッドのレス取得 | `GET /api/threads/{id}/responses`, `.../responses/new`, `.../responses/search` | api.threads.responses 等 |
| プロフィールのスレッド続き | `GET /api/profile/threads/more`, `GET /api/user/{id}/threads/more` | api.profile.threads.more, api.user.threads.more |
| 居住地履歴 | `GET /api/user/{id}/residence-history` | api.user.residence-history |
| 通報既存チェック | `GET /api/reports/existing` | api.reports.existing |
| コイン残高 | `GET /api/coins/balance` | api.coins.balance |
| アップロード上限（debug 時のみ） | `GET /api/upload-limits` | api.upload-limits |

- 上記には **`throttle:api`**（60回/分・user_id または IP）を適用済みです。
- **API 直叩き制限**: Cloudflare で **URI パス contains /api** にレート制限や WAF をかければ、上記すべてを対象にできます。

**まとめ（推奨）**

- **管理者**: URI パス **contains /{ADMIN_PREFIX の値}**（例: `/admin` または `/manage`）。
- **検索**: URI パス **contains /search**（`/search` と `/api/search/more` の両方にヒット）。
- **API 直叩き**: **contains /api** で一括対応可能。

### .env 本番分離（運用で実施）

- **リポジトリ**: `.env` と `.env.production` は `.gitignore` に含まれており、コミットされない。
- **本番**: 本番サーバーで別の `.env` を配置し、下記の「本番 .env で必須・推奨項目」を満たす。
- **テンプレート**: `.env.example` を元に本番用 `.env` を作成し、秘密情報は本番のみの値にする。`.env.production` は gitignore 済みのため、本番サーバーで手元の `.env.production` を `.env` としてコピーする運用も可能。

**本番 .env に記載すべき内容（完了の目安）**

以下が本番の `.env` に含まれていれば、.env 本番分離は一通り完了しているとみなせます。

| 項目 | 本番で必須の値・内容 |
|------|----------------------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | 本番用に `php artisan key:generate` で生成した値（漏洩しないこと） |
| `APP_URL` | 本番のURL（例: `https://your-domain.com`） |
| **DB_*** | 本番用DBの接続情報（`DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` 等） |
| **SESSION_DRIVER** | 本番では `database` または `redis` 推奨（`file` はマルチサーバーで不向き） |
| **ADMIN_PREFIX** | 管理者URLパス（例: `manage`）。未設定だと起動時にランダムになり運用で分からなくなる |
| **ADMIN_USER` / `ADMIN_PASSWORD` | 管理画面 Basic 認証のID/パスワード（強めのパスワードを設定） |

**本番で「設定しておくべき」項目（機能を使う場合）**

| 項目 | 内容 |
|------|------|
| `FRONTEND_URL` | フロントエンドのオリジン（CORS 用）。SPA 等で別オリジンなら必須 |
| `CSP_ENABLED` | `true` 推奨 |
| `VERIPHONE_API_KEY` | 電話番号検証を使う場合。本番では `VERIPHONE_SKIP_WHEN_NO_KEY=true` にしない |
| `OPENAI_API_KEY` | 翻訳APIを使う場合 |
| `SAFEBROWSING_ENABLED` | `true` で URL 安全チェックを有効化。`false` にすると Safe Browsing API を呼ばずスキップ |
| `SAFEBROWSING_API_KEY` | 有効時は必須（Google Safe Browsing）。未設定なら投稿は拒否（安全側） |
| `CLAMAV_ENABLED` | メディアのウイルススキャンを使う場合。`SKIP_MEDIA_VALIDATION_TOOLS` は本番では `false` |
| `SENTRY_LARAVEL_DSN` / `SENTRY_ENVIRONMENT` | エラー監視（Sentry）を使う場合。`SENTRY_ENVIRONMENT=production` など |
| `MAIL_*` | 本番でメール送信（認証コード等）を行う場合のSMTP等の設定 |

**本番で「true にしない」項目**

- `APP_DEBUG` → 必ず `false`
- `VERIPHONE_SKIP_WHEN_NO_KEY` → 本番では `true` にしない（未設定または `false`）
- `SHOW_VERIFICATION_CODE_ON_SCREEN` → 本番では `true` にしない
- `TRANSLATION_DEBUG_ALERT` → 本番では有効にしない
- `SKIP_MEDIA_VALIDATION_TOOLS` → 本番では `false` 推奨

---

## リソース上限・入力サイズ・キュー・CSRF の点検結果

### リソース上限をつけるべき箇所

| 箇所 | 状態 | 備考 |
|------|------|------|
| スレッド作成数/日 | ✅ 実装済み | `ThreadController::store` で 1 日 2 件まで |
| 1 スレッドあたりレス数 | ✅ 実装済み | `config('performance.thread.max_responses')` = 500、`ResponseController` でチェック |
| レス本文の文字数 | ✅ 対応済み | `ResponseController::store` で `body` に `max:1000` を追加（コインは「100文字ごとに1」のため、本文上限がないとコストが無制限になり得た） |
| 返信（reply）本文の文字数 | ✅ 対応済み | `ResponseController::reply` で `body` に `max:1000` を追加（同上） |
| 返信（reply）のメディアファイルサイズ | ✅ 対応済み | `ResponseController::reply` で `media_file` に `max:10240`（10MB）を追加（`store` と同様） |
| スレッド画像サイズ | ✅ 実装済み | バリデーションには `max` なしだが `MediaFileValidationService` で 1.5MB を検証 |
| レート制限 | ✅ 実装済み | `bootstrap/app.php` の `RateLimiter::for(...)` と各ルートの `throttle` |

### 入力サイズ上限（バリデーション）の点検

| 入力 | 上限 | 状態 |
|------|------|------|
| スレッド title / body / tag | max:50 / max:1000 / max:100 | ✅ 実装済み |
| レス本文（store） | max:1000 | ✅ 対応済み |
| 返信本文（reply） | max:1000 | ✅ 対応済み |
| ログイン email / password | max:255 | ✅ 対応済み（長大入力による DoS 軽減） |
| 管理画面お知らせ配信 body / title / title_key / body_key | max:2000 / max:255 / max:255 / max:255 | ✅ 対応済み（DB の body 制約 2000 と一致） |
| 通報理由・改善要望・お知らせ返信 | 既存のとおり max あり | ✅ 変更なし |

- **1000 文字と DB**: `responses.body` は `text` 型（MySQL で約 65KB）。1000 文字（UTF-8 でも数 KB）は問題なく格納できる。1000 はアプリ側の入力上限であり、DB の限界ではない。

### キューの実装

- **Laravel 標準**: `config/queue.php` および `database/migrations/..._create_jobs_table.php` により、キュー基盤は利用可能。
- **メール**: `App\Mail\AbnormalLoginMail` は `Queueable` トレイトを使用するが、`ShouldQueue` を実装していないため、デフォルトでは **同期的に送信** される。
- **アプリケーション層**: `dispatch()` や独自の `Job` クラスによるキュー投入は **未使用**。重い処理（メール送信・外部 API 呼び出しなど）を非同期化するキュー処理は **現時点では実装されていない**。
- **推奨**: メール送信や Safe Browsing / 翻訳 API などを非同期にしたい場合は、`QUEUE_CONNECTION=database`（または `redis`）にし、該当 Mailable に `ShouldQueue` を実装する、または専用 Job を `dispatch()` する実装を検討する。

### キューなしでの負荷軽減策（現状の仕様）

キューは使っていないが、次の仕様で負荷・悪用を抑えている。

| 施策 | 内容 |
|------|------|
| **レート制限（throttle）** | 投稿系 10/分・user、30/分・IP。API 60/分・user、100/分・IP。検索 20/分。ログイン 20/分・IP など。 |
| **1スレッドあたりレス数上限** | 500 件で打ち切り（続きは別スレッド要望）。 |
| **スレッド作成数上限** | 1 日 2 件まで。 |
| **入力長の上限（max）** | 本文 max:1000、タイトル max:50 など。巨大なリクエストを早い段階で弾く。 |
| **HTTP クライアント制限** | 外部 API 用にタイムアウト・レスポンスサイズ上限（SecureHttpClientService）。 |

重い処理（メール・Safe Browsing・翻訳）は同期的だが、上記の制限で呼び出し頻度と入力サイズが抑えられている。

### 外部API利用上限（設定済み）

**はい、設定済みです。** 次の2段階で上限がかかっています。

1. **HTTPクライアント共通**（`SecureHttpClientService` 経由の全リクエスト）
   - **タイムアウト**: デフォルト 10 秒（`config/security.php` の `http_client.timeout`、環境変数 `HTTP_CLIENT_TIMEOUT`）
   - **最大リダイレクト**: デフォルト 0（リダイレクト禁止）
   - **最大レスポンスサイズ**: デフォルト 10MB（`http_client.max_response_size`、環境変数 `HTTP_CLIENT_MAX_RESPONSE_SIZE`）
   - ドメインホワイトリスト・内部IP遮断も適用（上記「実装済みセキュリティ対策」参照）

2. **API別の呼び出し回数制限**
   - **Safe Browsing**: 20 回/分（user または IP）。`SafeBrowsingService` 内で `RateLimiter::tooManyAttempts('safebrowsing:...', 20)` をチェック。
   - **Veriphone**: 5 回/分（IP と user の両方）。ルートに `throttle:veriphone` を付与。
   - **OpenAI（翻訳）**: 10 回/分（user または IP）。`TranslationService` 内で `RateLimiter::tooManyAttempts('openai:...', 10)` をチェック。

Safe Browsing・Veriphone・翻訳・Cloudflare ログ送信はいずれも `SecureHttpClientService` を使用しているため、上記のタイムアウト・レスポンスサイズ上限が共通でかかります。

### CSRF の実装

- **方針**: すべての Web ルート（`routes/web.php` および `routes/admin.php`）は `web` ミドルウェアを利用しており、Laravel 標準で **VerifyCsrfToken** が適用される。プロジェクト内に `VerifyCsrfToken` のカスタムクラスおよび `$except` の追加は **なし**。
- **フォーム**: 各 POST フォームに `@csrf` を記載。Blade 経由のフォームは CSRF トークン付与済み。
- **JavaScript（fetch/XHR）**: `thread-show.js`・`notifications-index.js`・`friends-index.js`・`thread-index.js` などで `X-CSRF-TOKEN` ヘッダに `csrf_token()` / `meta[name="csrf-token"]` を付与。
- **API 配下**: `/api` は GET のみで状態変更なしのため、CSRF の対象外で問題なし。
- **結論**: 状態を変更する POST/PUT/DELETE はすべて CSRF 検証の対象となり、**実装漏れはなし**。テストは `tests/Feature/Security/CsrfProtectionTest.php` を参照。
