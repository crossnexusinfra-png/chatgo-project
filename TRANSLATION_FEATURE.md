# API翻訳機能とDB構造

ルーム名・リプライ本文を、表示ユーザーの言語に合わせて GPT-4o mini で翻訳する機能の仕様とDB構造のまとめ。

---

## 1. 翻訳機能の概要

- **対象**: ルーム名（スレッドタイトル）、リプライ本文
- **エンジン**: OpenAI GPT-4o mini（`app/Services/TranslationService.php`）
- **元言語**: **送信時の表示言語**を優先。スレッド/レスポンス作成時に `source_lang` をDBに保存するため、送信者が後から言語を変更したり削除されても、元言語を正しく判定できる。
- **翻訳要否**: 表示言語（閲覧者の設定）と元言語（`source_lang`）を比較し、**異なる場合のみ**翻訳する。元言語はAPIには送らず、この判別にのみ利用する。
- **キャッシュ**: 翻訳結果は `translation_caches` に保存し、**1年間**再利用。1年経過後は表示時に再翻訳する。

---

## 2. DB構造

### 2.1 元言語の保存（送信時）

| テーブル | カラム | 型 | 説明 |
|----------|--------|-----|------|
| `threads` | `source_lang` | char(2) NULL | スレッド作成時の送信者表示言語（JA/EN）。送信者削除後も元言語判定に使用。 |
| `responses` | `source_lang` | char(2) NULL | レスポンス投稿時の送信者表示言語（JA/EN）。同上。 |

- スレッド作成時: `ThreadController::store` で `auth()->user()->language` を正規化して `threads.source_lang` と初回レスポンスの `responses.source_lang` に保存。
- レスポンス投稿時: `ResponseController::store` / `reply` で同様に `responses.source_lang` に保存。
- 続きスレッド作成時: `ThreadContinuationController` で親スレッドの `source_lang` を継承。
- 既存データ（`source_lang` が NULL）: 表示時は送信者ユーザーの `language` にフォールバック。送信者削除時は `EN`。

### 2.2 翻訳結果のキャッシュ

| テーブル | カラム | 型 | 説明 |
|----------|--------|-----|------|
| `translation_caches` | `id` | bigint PK | 主キー |
| | `thread_id` | bigint NULL FK(threads) | ルーム名の翻訳時のみ設定 |
| | `response_id` | bigint NULL FK(responses) | リプライ本文の翻訳時のみ設定 |
| | `source_lang` | char(2) | 元言語（キャッシュ保存時の記録） |
| | `target_lang` | char(2) | 翻訳先言語（表示言語） |
| | `translated_text` | text | 翻訳済みテキスト |
| | `translated_at` | timestamp | 翻訳実行日時（ここから1年で失効） |
| | `created_at`, `updated_at` | timestamp |  |

- 一意制約: `(thread_id, target_lang)`、`(response_id, target_lang)`。同一スレッド/レスポンス・同一表示言語につき1行。
- 有効期限: `translated_at` から1年以内のみ有効。超えた場合は再翻訳して `translated_at` を更新。

---

## 3. API翻訳依頼文（プレースホルダー置換）

翻訳APIには **元言語を送らない**。ターゲット言語とテキストのみを、以下のプレースホルダーで置換して送信する。

### 3.1 返信元無し（ルーム名・単体リプライ）

- 置換するプレースホルダー: **`{target_language}`**, **`{original_text}`**

```
Translate the following text into {target_language}.

Rules:
- Do not translate URLs, code, or emojis.
- Keep proper nouns and brand names accurate.
- Preserve slang meaning naturally.
- Keep formatting unchanged.
- Return only the translated text.

Text:
"""
{original_text}
"""
```

### 3.2 返信元あり（親投稿をコンテキストにした返信の翻訳）

- 置換するプレースホルダー: **`{target_language}`**, **`{parent_text}`**, **`{reply_text}`**

```
Translate the reply into {target_language} considering the context.

Rules:
- Do not translate URLs, code, or emojis.
- Keep proper nouns and brand names accurate.
- Preserve slang meaning naturally.
- Keep formatting unchanged.
- Return only the translated reply.

Original post:
"""
{parent_text}
"""

Reply:
"""
{reply_text}
"""
```

- `{target_language}`: "Japanese" または "English"（表示言語に応じて `TranslationService::langNameForPrompt` で変換）。

---

## 4. 処理フロー

1. **表示時**（スレッド詳細・レスポンス一覧）
   - 表示言語 `target_lang` = 閲覧者の表示言語（JA/EN）。
   - ルーム名: `threads.source_lang`（未設定時は送信者の `language`）を取得 → `source_lang` とする。
   - リプライ: `responses.source_lang`（未設定時は送信者の `language`）を取得 → `source_lang` とする。
2. **翻訳要否**
   - `TranslationService::shouldTranslate($source_lang, $target_lang)` が false（同言語）なら翻訳しない。原文をそのまま表示。
3. **キャッシュ参照**
   - ルーム名: `translation_caches` を `(thread_id, target_lang)` で検索。有効（1年以内）なら `translated_text` を表示。
   - リプライ: `(response_id, target_lang)` で検索。同様。
4. **API翻訳**
   - キャッシュなし or 期限切れの場合、上記の依頼文でプレースホルダーを置換して GPT-4o mini を呼び出し。
   - 取得した翻訳を `translation_caches` に保存（`source_lang` も保存）し、表示に使用。

---

## 5. 関連ファイル

| 役割 | ファイル |
|------|----------|
| 翻訳ロジック・API呼び出し | `app/Services/TranslationService.php` |
| 翻訳キャッシュモデル | `app/Models/TranslationCache.php` |
| スレッド詳細・レス一覧での翻訳適用 | `app/Http/Controllers/ThreadController.php`（`applyTranslationsForThreadShow`, `applyTranslationsForResponses`） |
| スレッド/レス作成時の source_lang 保存 | `app/Http/Controllers/ThreadController.php`, `ResponseController.php`, `ThreadContinuationController.php` |
| 翻訳キャッシュテーブル作成 | `database/migrations/2026_02_11_000000_create_translation_caches_table.php` |
| threads/responses の source_lang 追加 | `database/migrations/2026_02_11_100000_add_source_lang_to_threads_and_responses.php` |
| OpenAI 設定 | `config/services.php`（`openai.api_key`）、`.env`（`OPENAI_API_KEY`） |
| 外部API許可 | `app/Services/SecureHttpClientService.php`（`api.openai.com` を許可） |

---

## 6. 設定

- `.env` に `OPENAI_API_KEY=sk-...` を設定する。
- 未設定時は翻訳APIを呼ばず、原文のまま表示する。
