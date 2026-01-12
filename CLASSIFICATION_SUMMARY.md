# プロジェクト分類ルールまとめ（修正後）

## 修正内容

### ✅ 実施した修正
1. **`header.js` → `common-header.js`にリネーム**
   - 共通JSの命名規則に統一
   - `layouts/header.blade.php`での参照も更新

---

## 1. CSSファイルの分類

### 現在のCSSファイル構成
```
public/css/
├── admin.css           # 管理者ページ専用（機能別）
├── app.css             # 認証ページ用（Tailwindベース）
├── bbs.css             # 共通スタイル（全ページで使用）
├── error-pages.css     # エラーページ専用（機能別）
├── friends.css         # フレンド機能専用（機能別）
├── inline-styles.css   # インラインスタイル用
├── notifications.css   # 通知機能専用（機能別）
├── profile.css         # プロフィール専用（機能別）
└── thread-show.css     # スレッド詳細ページ専用（ページ別）
```

### 分類ルール

#### 1. **共通スタイル**
- **ファイル**: `bbs.css`, `inline-styles.css`
- **用途**: 全ページで使用される基本スタイル
- **読み込み**: `layouts/app.blade.php`で直接読み込み

#### 2. **機能別スタイル**
- **命名規則**: `{機能名}.css`
- **例**: `profile.css`, `friends.css`, `admin.css`, `notifications.css`
- **用途**: 特定の機能全体で使用されるスタイル
- **読み込み**: `@push('styles')`を使用

#### 3. **ページ専用スタイル**
- **命名規則**: `{ページ名}.css`
- **例**: `thread-show.css`（スレッド詳細ページ専用の特殊なスタイル）
- **用途**: 特定のページ専用の特殊なスタイル（チャット形式のUIなど）
- **読み込み**: `@push('styles')`を使用

#### 4. **認証ページ用**
- **ファイル**: `app.css`
- **用途**: 認証関連ページのみ（独自レイアウトを使用）
- **読み込み**: 認証ページの`<head>`で直接読み込み

### 分類の考え方
- **機能別**: 複数ページで使用されるスタイル → `{機能名}.css`
- **ページ別**: 特定のページ専用の特殊なスタイル → `{ページ名}.css`

**結論**: 現状の分類は適切。`thread-show.css`はスレッド詳細ページ専用の特殊なスタイル（チャット形式のUI）のため、ページ名ベースの命名が適切。

---

## 2. JavaScriptファイルの分類

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
├── common-utils.js                 # 共通: ユーティリティ関数
├── common.js                       # 共通: 共通機能
├── friends-index.js               # フレンド: 一覧ページ
├── notifications-index.js         # 通知: 一覧ページ
├── profile-edit.js                # プロフィール: 編集
├── profile-index.js               # プロフィール: 一覧
├── profile-show.js                # プロフィール: 詳細
├── thread-category.js             # スレッド: カテゴリ
├── thread-index.js                # スレッド: 一覧
├── thread-search.js               # スレッド: 検索
├── thread-show.js                 # スレッド: 詳細
└── thread-tag.js                  # スレッド: タグ
```

### 分類ルール

#### 1. **共通JS**
- **命名規則**: `common-*.js`
- **ファイル**: 
  - `common-header.js` - ヘッダー機能（検索フォームのバリデーションなど）
  - `common-utils.js` - ユーティリティ関数
  - `common.js` - 共通のイベントハンドラなど
- **読み込み**: 
  - `common.js`, `common-utils.js`: `layouts/scripts.blade.php`
  - `common-header.js`: `layouts/header.blade.php`

#### 2. **機能別JS**
- **命名規則**: `{機能名}-{ページ名}.js`
- **例**: 
  - `thread-index.js`, `thread-show.js`, `thread-search.js`
  - `profile-index.js`, `profile-edit.js`, `profile-show.js`
- **用途**: 特定のページ/機能専用のJavaScript
- **読み込み**: 各viewファイルの最後で直接読み込み

#### 3. **認証関連JS**
- **命名規則**: `auth-{機能}.js`
- **例**: `auth-register.js`, `auth-sms-verification.js`
- **用途**: 認証関連ページ専用

#### 4. **管理者用JS**
- **命名規則**: `admin-{機能}.js`
- **例**: `admin-messages.js`, `admin-report-detail-response.js`
- **用途**: 管理者ページ専用

### 命名規則の統一性
✅ **統一されている点**:
- 共通JSは`common-`プレフィックスで統一
- 機能別JSは`{機能名}-{ページ名}.js`で統一
- 認証・管理者JSは`auth-`, `admin-`プレフィックスで統一

---

## 3. Viewファイルのフォルダ分け

### 基本構造
```
resources/views/
├── admin/          # 管理者専用ページ
├── auth/           # 認証関連ページ（ログイン、登録、認証など）
├── components/     # 再利用可能なコンポーネント
├── errors/         # エラーページ（403, 404, 500など）
├── friends/        # フレンド機能
├── layouts/        # レイアウトテンプレート（app.blade.phpなど）
├── notifications/  # 通知機能
├── profile/        # プロフィール関連
└── threads/        # スレッド関連
    └── partials/   # スレッド用の部分テンプレート
```

### 命名規則
- **フォルダ名**: 機能単位で分ける（例: `threads`, `profile`, `admin`）
- **ファイル名**: スネークケース（例: `thread-show.blade.php`, `profile-edit.blade.php`）
- **部分テンプレート**: `partials/`フォルダに配置

### レイアウトの使い分け
- **通常ページ**: `@extends('layouts.app')`を使用
- **認証ページ**: 独自のHTML構造を使用（設計上の意図）

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

---

## 5. 修正後の評価

### ✅ 適切に分類されている点
1. **Viewファイル**: 機能別に適切に分類されている
2. **CSSファイル**: 機能別とページ専用で適切に分類されている
3. **JavaScriptファイル**: 共通と機能別で適切に分類されている
4. **ルーティング**: RESTfulで適切に設計されている

### ✅ 実施済みの改善
1. **共通JSの命名統一**: `header.js` → `common-header.js`にリネーム完了

### 📝 分類の考え方まとめ

#### CSS
- **機能別**: 複数ページで使用 → `{機能名}.css`
- **ページ別**: 特定ページ専用の特殊スタイル → `{ページ名}.css`

#### JavaScript
- **共通**: 全ページで使用 → `common-*.js`
- **機能別**: 特定機能/ページ専用 → `{機能名}-{ページ名}.js`

#### View
- **機能別**: 機能単位でフォルダ分け
- **部分テンプレート**: `partials/`フォルダに配置

---

## 6. まとめ

**結論**: プロジェクトの構造は適切に分類されており、命名規則も一貫性があります。

- ✅ CSSは機能別とページ専用で使い分けが明確
- ✅ JavaScriptは共通と機能別で適切に分類
- ✅ Viewファイルは機能単位で整理されている
- ✅ ルーティングはRESTfulで適切に設計されている

**修正完了**: 共通JSの命名規則を統一し、プロジェクト構造がより一貫性のあるものになりました。
