# プロジェクト構造ガイド

## 1. Viewファイルのフォルダ分けルール

### 基本構造
```
resources/views/
├── admin/          # 管理者専用ページ
├── auth/           # 認証関連ページ（ログイン、登録、認証など）
├── components/     # 再利用可能なコンポーネント
├── errors/         # エラーページ（403, 404, 500など）
├── friends/        # フレンド機能
├── layouts/       # レイアウトテンプレート（app.blade.phpなど）
├── notifications/  # 通知機能
├── profile/       # プロフィール関連
└── threads/       # スレッド関連
    └── partials/  # スレッド用の部分テンプレート
```

### 命名規則
- **フォルダ名**: 機能単位で分ける（例: `threads`, `profile`, `admin`）
- **ファイル名**: スネークケース（例: `thread-show.blade.php`, `profile-edit.blade.php`）
- **部分テンプレート**: `partials/` フォルダに配置（例: `threads/partials/thread-item-list.blade.php`）

### レイアウトの使い分け
- **通常ページ**: `@extends('layouts.app')` を使用
- **認証ページ**: 独自のHTML構造を使用（`layouts.app`を継承しない）
  - `auth/register.blade.php`
  - `auth/login.blade.php`
  - `auth/sms-verification.blade.php`
  - など

---

## 2. CSSファイルの分類ルール

### 現在のCSSファイル構成
```
public/css/
├── admin.css           # 管理者ページ専用
├── app.css             # 認証ページ用（Tailwindベース）
├── bbs.css             # 共通スタイル（全ページで使用）
├── error-pages.css     # エラーページ専用
├── friends.css         # フレンド機能専用
├── inline-styles.css  # インラインスタイル用
├── notifications.css   # 通知機能専用
├── profile.css         # プロフィール専用
└── thread-show.css     # スレッド詳細ページ専用
```

### 分類ルール

#### 1. **共通スタイル** (`bbs.css`, `inline-styles.css`)
- **用途**: 全ページで使用される基本スタイル
- **読み込み方法**: `layouts/app.blade.php`で直接読み込み
- **内容**: ヘッダー、フッター、ボタン、フォーム、モーダルなど

#### 2. **機能別スタイル** (例: `profile.css`, `friends.css`, `notifications.css`)
- **命名規則**: `{機能名}.css` または `{ページ名}.css`
- **用途**: 特定の機能/ページ専用のスタイル
- **読み込み方法**: `@push('styles')` を使用
  ```blade
  @push('styles')
      <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
  @endpush
  ```

#### 3. **認証ページ用** (`app.css`)
- **用途**: 認証関連ページのみ（独自レイアウトを使用）
- **読み込み方法**: 認証ページの`<head>`で直接読み込み

#### 4. **エラーページ用** (`error-pages.css`)
- **用途**: エラーページ（403, 404, 500など）専用
- **読み込み方法**: `@push('styles')` を使用

### 推奨される改善点

#### ✅ 現在の状態（修正後）
1. **CSS命名について**: 
   - `thread-show.css`はスレッド詳細ページ専用の特殊なスタイル（チャット形式のUI）のため、ページ名ベースの命名で問題なし
   - 他のスレッドページ（index, search, category, tag）は`bbs.css`を使用
   - 機能別CSS（`profile.css`, `friends.css`, `admin.css`など）は機能名で統一されている
2. **認証ページ**: 認証ページは独自レイアウトを使用（設計上の意図）

#### 📝 命名規則の考え方
- **機能ベース**: 複数ページで使用されるスタイル（例: `profile.css`, `admin.css`）
- **ページベース**: 特定のページ専用の特殊なスタイル（例: `thread-show.css`はチャット形式のUI専用）

**結論**: 現状の命名規則で問題なし。`thread-show.css`はスレッド詳細ページ専用の特殊なスタイルのため、ページ名ベースの命名が適切

---

## 3. JavaScriptファイルの分類ルール

### 現在のJSファイル構成（修正後）
```
public/js/
├── admin-messages.js              # 管理者: お知らせ管理
├── admin-report-detail-response.js # 管理者: レスポンス通報詳細
├── auth-email-verification.js     # 認証: メール認証
├── auth-profile-email-verification.js # 認証: プロフィールメール認証
├── auth-profile-sms-verification.js  # 認証: プロフィールSMS認証
├── auth-register.js               # 認証: 登録
├── auth-sms-verification.js       # 認証: SMS認証
├── common-header.js                # 共通: ヘッダー機能（修正済み）
├── common-utils.js                # 共通: ユーティリティ関数
├── common.js                      # 共通: 共通機能
├── friends-index.js               # フレンド: 一覧ページ
├── notifications-index.js         # 通知: 一覧ページ
├── profile-edit.js                # プロフィール: 編集
├── profile-index.js                # プロフィール: 一覧
├── profile-show.js                 # プロフィール: 詳細
├── thread-category.js              # スレッド: カテゴリ
├── thread-index.js                 # スレッド: 一覧
├── thread-search.js                # スレッド: 検索
├── thread-show.js                  # スレッド: 詳細
└── thread-tag.js                   # スレッド: タグ
```

### 分類ルール

#### 1. **共通JS** (`common.js`, `common-utils.js`, `common-header.js`)
- **用途**: 全ページで使用される共通機能
- **読み込み方法**: 
  - `common.js`, `common-utils.js`: `layouts/scripts.blade.php`で読み込み
  - `common-header.js`: `layouts/header.blade.php`で読み込み
- **内容**: 
  - `common-utils.js`: ユーティリティ関数
  - `common.js`: 共通のイベントハンドラなど
  - `common-header.js`: ヘッダー機能（検索フォームのバリデーションなど）

#### 2. **機能別JS** (例: `thread-*.js`, `profile-*.js`)
- **命名規則**: `{機能名}-{ページ名}.js` または `{機能名}-{機能}.js`
- **用途**: 特定のページ/機能専用のJavaScript
- **読み込み方法**: 各viewファイルの最後で直接読み込み
  ```blade
  <script src="{{ asset('js/thread-show.js') }}"></script>
  ```

#### 3. **認証関連JS** (`auth-*.js`)
- **命名規則**: `auth-{機能}.js`
- **用途**: 認証関連ページ専用

#### 4. **管理者用JS** (`admin-*.js`)
- **命名規則**: `admin-{機能}.js`
- **用途**: 管理者ページ専用

### 命名規則の統一性

#### ✅ 現在の良い点（修正後）
- 機能名でプレフィックスが統一されている（`thread-`, `profile-`, `auth-`, `admin-`）
- ページ名が明確（`index`, `show`, `edit`など）
- 共通JSは`common-`プレフィックスで統一（`common-header.js`, `common-utils.js`, `common.js`）

#### ✅ 修正完了
- ✅ `header.js` → `common-header.js`にリネーム完了（共通JSの命名規則に統一）

---

## 4. ViewからDBへのルーティングフロー

### 基本的なフロー

```
1. ユーザーリクエスト
   ↓
2. Route定義 (routes/web.php または routes/admin.php)
   ↓
3. コントローラー (app/Http/Controllers/*.php)
   ↓
4. モデル (app/Models/*.php)
   ↓
5. データベース
   ↓
6. コントローラーでデータを取得・処理
   ↓
7. Viewにデータを渡す (return view('view名', ['data' => $data]))
   ↓
8. Bladeテンプレートで表示
```

### 具体例: スレッド詳細ページ

#### 1. **ルート定義** (`routes/web.php`)
```php
Route::get('/threads/{thread}', [ThreadController::class, 'show'])
    ->name('threads.show');
```

#### 2. **コントローラー** (`app/Http/Controllers/ThreadController.php`)
```php
public function show(Thread $thread)
{
    // データベースからデータを取得
    $thread = Thread::with(['responses', 'user'])->findOrFail($thread->id);
    
    // ビジネスロジック処理
    $isResponseLimitReached = $thread->responses_count >= 1000;
    
    // Viewにデータを渡す
    return view('threads.show', [
        'thread' => $thread,
        'isResponseLimitReached' => $isResponseLimitReached,
        'lang' => $lang
    ]);
}
```

#### 3. **モデル** (`app/Models/Thread.php`)
```php
class Thread extends Model
{
    // リレーション定義
    public function responses()
    {
        return $this->hasMany(Response::class);
    }
    
    // スコープ定義
    public function scopeByTag($query, $tag)
    {
        return $query->where('tag', $tag);
    }
}
```

#### 4. **View** (`resources/views/threads/show.blade.php`)
```blade
@extends('layouts.app')

@section('content')
    <h1>{{ $thread->title }}</h1>
    <p>{{ $thread->body }}</p>
    
    @foreach($thread->responses as $response)
        <div>{{ $response->body }}</div>
    @endforeach
@endsection
```

### ルーティングの分類

#### **Webルート** (`routes/web.php`)
- 一般ユーザー向けのルート
- 認証が必要なルートは`middleware('auth')`で保護
- 例: スレッド一覧、プロフィール、通知など

#### **管理者ルート** (`routes/admin.php`)
- 管理者専用のルート
- `middleware(['web', 'admin.basic', 'admin.visit'])`で保護
- プレフィックス: `config('admin.prefix')`で設定
- 例: 通報管理、お知らせ配信、ログ管理など

### データフローのパターン

#### パターン1: 通常のページ表示
```
GET /threads/{thread}
→ ThreadController@show
→ Threadモデルからデータ取得
→ view('threads.show')を返す
```

#### パターン2: フォーム送信（POST）
```
POST /threads
→ ThreadController@store
→ バリデーション
→ 画像アップロード処理
→ スレッド作成数上限チェック
→ R18タグ・R18スレッドチェック
→ URLの安全性チェック（SafeBrowsingService）
→ スパム検出チェック（SpamDetectionService）
  - スレッド名（title）のスパムチェック
  - 1スレ目（body）のスパムチェック
→ コイン消費（CoinService）
→ Threadモデルでデータ保存
→ リダイレクト
```

#### パターン3: AJAXリクエスト
```
GET /threads/{thread}/responses
→ ThreadController@getResponses
→ JSON形式でデータを返す
→ JavaScriptでDOM操作
```

---

## 5. サービス層の構造

### サービスファイル一覧
```
app/Services/
├── CoinService.php              # コイン機能（獲得、消費、報酬計算）
├── FriendService.php            # フレンド機能
├── LanguageService.php          # 多言語対応
├── MediaFileProcessingService.php # メディアファイル処理（画像再エンコード、メタデータ削除）
├── MediaFileValidationService.php # メディアファイル検証
├── PhoneNumberService.php       # 電話番号処理
├── SafeBrowsingService.php      # URL安全性チェック（Google Safe Browsing API）
├── SpamDetectionService.php     # スパム検出（NGワード、類似度チェック）
└── VeriphoneService.php         # 電話番号検証（Veriphone API）
```

### 主要サービスの役割

#### **SpamDetectionService**
- **用途**: スパム投稿の検出
- **使用箇所**: スレッド作成時、レスポンス送信時
- **機能**:
  - NGワードフィルタ（完全一致）
  - 類似率チェック（Levenshtein距離、3-gram Jaccard）
  - URL類似度チェック
  - URL投稿回数チェック

#### **SafeBrowsingService**
- **用途**: URLの安全性チェック
- **使用箇所**: スレッド作成時、レスポンス送信時
- **機能**: Google Safe Browsing APIを使用して危険なURLを検出

#### **CoinService**
- **用途**: コイン機能の管理
- **機能**:
  - コインの獲得・消費
  - 広告視聴報酬の計算
  - 連続ログイン報酬の計算
  - レスポンス送信コストの計算

#### **LanguageService**
- **用途**: 多言語対応
- **機能**: 言語設定の取得、翻訳文字列の取得

---

## 6. 現在の構造の評価と改善提案

### ✅ 良い点
1. **機能別のフォルダ分け**: View、CSS、JSが機能単位で整理されている
2. **命名規則の統一**: プレフィックス（`thread-`, `profile-`, `auth-`）が一貫している
3. **レイアウトの再利用**: `layouts/app.blade.php`で共通レイアウトを管理
4. **@push/@stackの活用**: ページ固有のCSSを適切に分離

### ✅ 現在の状態（修正後）

#### 1. **CSS命名について**
- ✅ `thread-show.css`はスレッド詳細ページ専用の特殊なスタイル（チャット形式のUI）のため、ページ名ベースの命名で問題なし
- ✅ 他のスレッドページ（index, search, category, tag）は`bbs.css`を使用
- ✅ 機能別CSS（`profile.css`, `friends.css`, `admin.css`など）は機能名で統一されている

#### 2. **認証ページのレイアウト**
- 認証ページは独自レイアウトを使用（設計上の意図）

#### 3. **JSファイルの命名** ✅ 修正完了
- ✅ `header.js` → `common-header.js`にリネーム完了
- ✅ 共通JSは`common-`プレフィックスで統一済み

#### 4. **CSS/JSの読み込み方法**
- ✅ ページ固有のCSSは`@push('styles')`で統一
- ✅ ページ固有のJSは各viewファイルの最後で直接読み込み（適切）

---

## 7. ベストプラクティス

### Viewファイル
1. **レイアウトの継承**: 可能な限り`layouts.app`を継承
2. **部分テンプレート**: 再利用可能な部分は`partials/`に配置
3. **命名規則**: スネークケースで統一

### CSSファイル
1. **共通スタイル**: `bbs.css`に配置
2. **ページ固有スタイル**: `@push('styles')`で読み込み
3. **命名規則**: 機能名で統一（例: `thread.css`, `profile.css`）

### JavaScriptファイル
1. **共通JS**: `common-*.js`で統一（`common-header.js`, `common-utils.js`, `common.js`）
2. **ページ固有JS**: 各viewファイルの最後で読み込み
3. **命名規則**: `{機能名}-{ページ名}.js`で統一

### ルーティング
1. **RESTful設計**: 可能な限りRESTfulなルート設計
2. **ミドルウェア**: 認証が必要なルートは`middleware('auth')`で保護
3. **名前付きルート**: `->name()`でルート名を定義し、`route()`ヘルパーで使用

### コンテンツ審査・スパム検出
1. **スパム検出**: スレッド作成時とレスポンス送信時の両方で実装
2. **NGワードフィルタ**: 19個のNGワードリストで完全一致検出
3. **類似度チェック**: Levenshtein距離と3-gram Jaccardで類似投稿を検出
4. **URL安全性チェック**: Google Safe Browsing APIで危険なURLを検出
5. **URL投稿制限**: 1日あたり5回まで

---

## 8. コンテンツ審査・スパム検出機能

### 実装場所
- **サービス**: `app/Services/SpamDetectionService.php`
- **使用箇所**: 
  - `app/Http/Controllers/ThreadController.php` - スレッド作成時
  - `app/Http/Controllers/ResponseController.php` - レスポンス送信時

### 審査対象
1. **スレッド作成時**:
   - スレッド名（title）のスパムチェック
   - 1スレ目（body）のスパムチェック
   - URLの安全性チェック（Google Safe Browsing API）

2. **レスポンス送信時**:
   - レスポンス本文（body）のスパムチェック
   - URLの安全性チェック（Google Safe Browsing API）

### スパム検出の種類

#### 1. **NGワードフィルタ（完全一致）**
- 19個のNGワードリスト（日本語・英語）
- 大文字小文字を区別しない部分一致検出

#### 2. **類似率チェック**
- NGワードとの類似度チェック（Levenshtein距離: 80%、3-gram Jaccard: 70%）
- 過去の投稿との類似度チェック（12時間以内の同一ユーザーの投稿と比較）

#### 3. **URL類似度チェック**
- 12時間以内の類似URL投稿をチェック

#### 4. **URL投稿回数チェック**
- 1日あたり5回以上のURL投稿を制限

### 審査の流れ

#### スレッド作成時
```
スレッド作成リクエスト
  ↓
1. バリデーション
  ↓
2. 画像アップロード処理
  ↓
3. スレッド作成数上限チェック
  ↓
4. R18タグ・R18スレッドチェック
  ↓
5. URLの安全性チェック（body）
  ↓
6. スレッド名（title）のスパムチェック
  ↓
7. 1スレ目（body）のスパムチェック
  ↓
8. コイン消費
  ↓
9. スレッド作成
```

#### レスポンス送信時
```
レスポンス送信リクエスト
  ↓
1. バリデーション
  ↓
2. ファイルアップロード処理
  ↓
3. URLの安全性チェック（body）
  ↓
4. コイン消費
  ↓
5. レスポンス本文（body）のスパムチェック
  ↓
6. レスポンス作成
```

### エラーメッセージ
- NGワード検出: `spam_ng_word_detected`
- 類似レスポンス検出: `spam_similar_response_detected`
- 類似URL検出: `spam_similar_url_detected`
- URL投稿上限超過: `spam_url_post_limit_exceeded`
- 危険なURL検出: `url_check_unsafe`

---

## 9. まとめ

### 現在の構造の適切性（修正後）
- **Viewファイル**: ✅ 機能別に適切に分類されている
- **CSSファイル**: ✅ 機能別・ページ別に適切に分類されている
  - 機能別CSS: `profile.css`, `friends.css`, `admin.css`など
  - ページ専用CSS: `thread-show.css`（スレッド詳細ページ専用の特殊なスタイル）
- **JavaScriptファイル**: ✅ 機能別に適切に分類されている
  - 共通JS: `common-*.js`で統一済み
  - 機能別JS: `{機能名}-{ページ名}.js`で統一
- **ルーティング**: ✅ RESTfulで適切に設計されている

### 実施済みの改善項目
1. ✅ 共通JSの命名統一（`header.js` → `common-header.js`にリネーム完了）
2. ✅ スレッド作成時のスパム検出チェック実装（スレッド名と1スレ目）

### 現在の分類の評価
**結論**: プロジェクトの構造は適切に分類されており、命名規則も一貫性があります。
- ✅ CSSは機能別とページ専用で使い分けが明確
- ✅ JavaScriptは共通と機能別で適切に分類
- ✅ Viewファイルは機能単位で整理されている
- ✅ スパム検出機能がスレッド作成時とレスポンス送信時の両方で実装されている
- ✅ コンテンツ審査機能（NGワード、類似度チェック、URL安全性チェック）が実装されている
