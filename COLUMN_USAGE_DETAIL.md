# カラム使用状況の詳細説明

## 1. usersテーブルの name, username, user_identifier

### **name** カラム
**使用状況**:
- **登録時のみ設定**: ユーザー登録時に `username` と同じ値が設定されます（`AuthController.php` 246行目）
  ```php
  $registrationData['name'] = $request->username; // name にはusernameと同じ値を設定
  ```
- **その後は更新されない**: プロフィール編集などでも `name` は更新されていません
- **用途**: 後方互換性のため残されている可能性がありますが、現在は `username` が主に使用されています

### **username** カラム
**使用状況**:
- **スレッド・レスポンスの作成者として使用**: `threads.user_name` や `responses.user_name` に保存されます
- **リレーションのキーとして使用**: `User::threads()` や `User::responses()` で `username` を使用
- **検索・フィルタリングで使用**: ユーザーのスレッドやレスポンスを検索する際に使用
- **変更不可**: `User` モデルの `boot()` メソッドで変更が禁止されています（67-68行目）
  ```php
  if (isset($original['username']) && $user->username !== $original['username']) {
      $user->username = $original['username'];
  }
  ```

### **user_identifier** カラム
**使用状況**:
- **表示用の識別子**: ユーザー名の後に `@user_identifier` として表示されます
- **一意の識別子**: 5-15文字の小文字とアンダースコアのみ（`/^[a-z_]+$/`）
- **登録時に設定**: ユーザーが指定するか、ランダム生成されます
- **変更不可**: `User` モデルの `boot()` メソッドで変更が禁止されています（70-71行目）
- **表示で使用**: レスポンス表示時に `username@user_identifier` の形式で表示されます

---

## 2. usersテーブルの認証コード

### **sms_verification_code** と **email_verification_code**

**重要な発見**: これらのカラムは**データベースには保存されていません**！

**実際の動作**:
- **Cacheに保存**: 認証コードは Laravel の Cache に保存されます
  - SMS認証コード: `Cache::put("sms_verification_{$phone}", $code, 300)` （5分間有効）
  - メール認証コード: `Cache::put("email_verification_{$email}", $code, 600)` （10分間有効）
- **認証のたびに生成**: 認証コードは認証のたびに新しく生成されます
- **一時的な保存**: Cacheに保存されるため、有効期限が切れると自動的に削除されます

**結論**: 
- ✅ **認証のたびに変わります**
- ✅ **データベースには保存されません**（Cacheに保存）
- ⚠️ **データベースのカラムは未使用の可能性があります**

---

## 3. usersテーブルの invite_code

### **invite_code** カラム

**使用状況**:
- **生成時に一度だけ設定**: `FriendService::generateInviteCode()` で生成されます
- **既存の場合は変更しない**: 既に `invite_code` がある場合は、それを返すだけです（`FriendService.php` 368行目）
  ```php
  if ($user->invite_code) {
      return $user->invite_code; // 既存のコードを返す
  }
  ```
- **変更不可**: 一度生成されたら変更されません
- **用途**: 他のユーザーを招待する際に使用するコード

**結論**: 
- ✅ **変更されることはありません**
- ✅ **一度生成されたら永続的に使用されます**

---

## 4. updated_at カラムの更新タイミング

### **users.updated_at**

**更新されるタイミング**:
- プロフィール編集時（`ProfileController::update()`）
  - メールアドレス、電話番号、居住地、自己紹介、言語設定の変更
- パスワード変更時
- その他、`$user->save()` や `$user->update()` が呼ばれた時

**Laravelの自動更新**: Eloquentモデルで `save()` や `update()` が呼ばれると自動的に `updated_at` が更新されます

---

### **threads.updated_at**

**更新されるタイミング**:
- スレッド作成時（`created_at` と同時に設定）
- スレッド編集時（タイトル、タグ、R18フラグ、画像の変更）
- レスポンス数が更新された時（`updateResponsesCountUp()`, `updateResponsesCountDown()`）
  ```php
  $this->increment('responses_count'); // これで updated_at も更新される
  ```
- アクセス数が更新された時（`updateAccessCountUp()`）
- 続きスレッド関連の更新時（`parent_thread_id`, `continuation_thread_id` の変更）

**注意**: `increment()` や `decrement()` メソッドも `updated_at` を更新します

---

### **responses.updated_at**

**更新されるタイミング**:
- レスポンス作成時（`created_at` と同時に設定）
- レスポンス編集時（本文、メディアファイルの変更）
- レスポンス削除時（ソフトデリートの場合）

**注意**: レスポンスは通常、作成後に編集されることは少ないため、`updated_at` は `created_at` と同じ値であることが多いです

---

## まとめ

| カラム | 変更頻度 | 用途 |
|--------|---------|------|
| `users.name` | 登録時のみ | 後方互換性のため（現在は未使用） |
| `users.username` | 変更不可 | スレッド・レスポンスの作成者として使用 |
| `users.user_identifier` | 変更不可 | 表示用の識別子（@user_identifier） |
| `users.sms_verification_code` | **未使用** | データベースには保存されず、Cacheに保存 |
| `users.email_verification_code` | **未使用** | データベースには保存されず、Cacheに保存 |
| `users.invite_code` | 変更不可 | 招待コード（一度生成されたら永続） |
| `users.updated_at` | プロフィール変更時 | プロフィール編集、パスワード変更など |
| `threads.updated_at` | スレッド変更時 | 編集、レスポンス数更新、アクセス数更新など |
| `responses.updated_at` | レスポンス変更時 | 編集時（通常は作成時のみ） |

---

## 推奨事項

### 1. **認証コードカラムの削除検討**
`sms_verification_code` と `email_verification_code` はデータベースに保存されていないため、カラムを削除することを検討してください。

### 2. **nameカラムの整理**
`name` カラムは現在ほとんど使用されていないため、将来的に削除することを検討してください。

