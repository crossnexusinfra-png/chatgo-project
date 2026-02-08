# データベーススキーマ詳細

## 1. users（ユーザー）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| user_id | PRIMARY KEY | - | ユーザーID（自動採番） |
| username | UNIQUE, NOT NULL | - | ユーザー名（一意、変更不可） |
| user_identifier | UNIQUE, NULL可 | 15文字 | ユーザー識別子（一意、変更不可） |
| email | UNIQUE, NOT NULL | - | メールアドレス（一意） |
| phone | UNIQUE, NOT NULL | 20文字 | 電話番号（一意） |
| password | NOT NULL | - | パスワード（ハッシュ化） |
| nationality | NOT NULL | 10文字 | 国籍（CHECK制約: 'JP', 'US', 'GB', 'CA', 'AU', 'OTHER'） |
| residence | NOT NULL | 10文字 | 居住地（CHECK制約: 'JP', 'US', 'GB', 'CA', 'AU', 'OTHER'） |
| birthdate | NULL可 | - | 生年月日 |
| sms_verified_at | NULL可 | - | SMS認証日時 |
| email_verified_at | NULL可 | - | メール認証日時 |
| is_verified | NOT NULL | - | 認証済みフラグ（boolean, default: false） |
| profile_image | NULL可 | - | プロフィール画像パス |
| bio | NULL可 | 100文字 | 自己紹介（text, CHECK制約: 100文字以内） |
| settings | NULL可 | - | ユーザー設定（JSON形式） |
| coins | NOT NULL | - | 保有コイン数（unsigned integer, default: 0） |
| last_login_date | NULL可 | - | 最終ログイン日 |
| consecutive_login_days | NOT NULL | - | 連続ログイン日数（unsigned integer, default: 0） |
| invite_code | UNIQUE, NULL可 | 20文字 | 招待コード（一意） |
| created_at | NOT NULL | - | 作成日時（ユーザー登録日時） |
| updated_at | NULL可 | - | 更新日時 |

---

## 2. threads（スレッド）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| thread_id | PRIMARY KEY | - | スレッドID（自動採番） |
| parent_thread_id | NULL可, FOREIGN KEY | - | 親スレッドID（FK → threads.thread_id, onDelete: set null） |
| continuation_thread_id | NULL可, FOREIGN KEY | - | 続きスレッドID（FK → threads.thread_id, onDelete: set null） |
| title | NOT NULL | 50文字 | スレッドタイトル |
| tag | NOT NULL | 100文字 | タグ（default: 'その他'） |
| is_r18 | NOT NULL | - | R18コンテンツフラグ（boolean, default: false） |
| image_path | NULL可 | - | スレッド画像パス |
| user_name | NOT NULL | - | 作成者名 |
| responses_count | NOT NULL | - | レスポンス数（unsigned integer, default: 0） |
| access_count | NOT NULL | - | アクセス数（integer, default: 0） |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |
| deleted_at | NULL可 | - | 削除日時（ソフトデリート） |

---

## 3. responses（レスポンス）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| response_id | PRIMARY KEY | - | レスポンスID（自動採番） |
| thread_id | NOT NULL, FOREIGN KEY | - | スレッドID（FK → threads.thread_id, onDelete: cascade） |
| parent_response_id | NULL可, FOREIGN KEY | - | 親レスポンスID（FK → responses.response_id, onDelete: cascade） |
| body | NOT NULL | - | レスポンス本文（text） |
| user_name | NOT NULL | - | 作成者名 |
| responses_num | NOT NULL | - | レスポンス番号（unsigned integer） |
| media_file | NULL可 | 255文字 | メディアファイルパス |
| media_type | NULL可 | 255文字 | メディアタイプ |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |

---

## 4. admin_messages（管理者メッセージ）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| id | PRIMARY KEY | - | メッセージID（自動採番） |
| user_id | NULL可, FOREIGN KEY | - | 送信先ユーザーID（FK → users.user_id, onDelete: cascade） |
| thread_id | NULL可, FOREIGN KEY | - | 関連スレッドID（FK → threads.thread_id, onDelete: cascade） |
| title_key | NULL可 | - | タイトルキー（多言語対応用） |
| body_key | NULL可 | - | 本文キー（多言語対応用） |
| title | NULL可 | - | タイトル（直接保存、フォールバック用） |
| body | NULL可 | 2000文字 | 本文（直接保存、フォールバック用, text, CHECK制約: 2000文字以内） |
| audience | NOT NULL | - | 配信対象（'all' | 'members'） |
| published_at | NULL可 | - | 公開日時 |
| allows_reply | NOT NULL | - | 返信許可フラグ（boolean, default: false） |
| unlimited_reply | NOT NULL | - | 無制限返信フラグ（boolean, default: false） |
| reply_used | NOT NULL | - | 返信使用済みフラグ（boolean, default: false） |
| parent_message_id | NULL可, FOREIGN KEY | - | 親メッセージID（FK → admin_messages.id, onDelete: cascade） |
| coin_amount | NULL可 | - | コイン報酬額（integer） |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |

---

## 5. reports（通報）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| report_id | PRIMARY KEY | - | 通報ID（自動採番） |
| user_id | NOT NULL, FOREIGN KEY | - | 通報者ID（FK → users.user_id, onDelete: cascade） |
| thread_id | NULL可, FOREIGN KEY | - | 対象スレッドID（FK → threads.thread_id, onDelete: cascade） |
| response_id | NULL可, FOREIGN KEY | - | 対象レスポンスID（FK → responses.response_id, onDelete: cascade） |
| reason | NOT NULL | - | 通報理由 |
| description | NULL可 | 300文字 | 詳細説明（text, CHECK制約: 300文字以内） |
| is_approved | NOT NULL | - | 承認フラグ（boolean, default: false） |
| approved_at | NULL可 | - | 承認日時 |
| flagged | NOT NULL, INDEX | - | 重要フラグ（boolean, default: false） |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |
| UNIQUE制約 | (user_id, thread_id) | - | 同一ユーザーは同一スレッドに1回のみ通報可能 |
| UNIQUE制約 | (user_id, response_id) | - | 同一ユーザーは同一レスポンスに1回のみ通報可能 |

---

## 6. suggestions（提案・要望）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| id | PRIMARY KEY | - | 提案ID（自動採番） |
| user_id | NULL可, INDEX | - | 提案者ID（FK → users.user_id） |
| message | NOT NULL | 1000文字 | 提案内容（text, CHECK制約: 1000文字以内） |
| completed | NOT NULL, INDEX | - | 処理完了フラグ（boolean, default: false） |
| starred | NOT NULL, INDEX | - | スター付与フラグ（boolean, default: false） |
| coin_amount | NULL可 | - | 報酬コイン額（unsigned tiny integer） |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |

---

## 7. friendships（フレンド関係）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| id | PRIMARY KEY | - | フレンド関係ID（自動採番） |
| user_id | NOT NULL, FOREIGN KEY, INDEX | - | ユーザーID（FK → users.user_id, onDelete: cascade） |
| friend_id | NOT NULL, FOREIGN KEY, INDEX | - | フレンドID（FK → users.user_id, onDelete: cascade） |
| friendship_date | NOT NULL | - | フレンドになった日時（timestamp） |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |
| UNIQUE制約 | (user_id, friend_id) | - | 同一ユーザー間のフレンド関係は1つのみ |

---

## 8. friend_requests（フレンド申請）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| id | PRIMARY KEY | - | 申請ID（自動採番） |
| from_user_id | NOT NULL, FOREIGN KEY, INDEX | - | 申請者ID（FK → users.user_id, onDelete: cascade） |
| to_user_id | NOT NULL, FOREIGN KEY, INDEX | - | 受信者ID（FK → users.user_id, onDelete: cascade） |
| status | NOT NULL, INDEX | - | 申請状態（enum: 'pending', 'accepted', 'rejected', default: 'pending'） |
| requested_at | NOT NULL | - | 申請日時（timestamp） |
| responded_at | NULL可 | - | 応答日時 |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |
| UNIQUE制約 | (from_user_id, to_user_id) | - | 同一ユーザー間の申請は1つのみ |

---

## 9. coin_sends（コイン送信）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| id | PRIMARY KEY | - | 送信ID（自動採番） |
| from_user_id | NOT NULL, FOREIGN KEY, INDEX | - | 送信者ID（FK → users.user_id, onDelete: cascade） |
| to_user_id | NOT NULL, FOREIGN KEY, INDEX | - | 受信者ID（FK → users.user_id, onDelete: cascade） |
| coins | NOT NULL | - | 送信コイン数（unsigned integer） |
| sent_at | NOT NULL, INDEX | - | 送信日時（timestamp） |
| next_available_at | NULL可 | - | 次回送信可能日時 |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |

---

## 10. thread_favorites（スレッドお気に入り）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| favorite_id | PRIMARY KEY | - | お気に入りID（自動採番） |
| user_id | NOT NULL, FOREIGN KEY | - | ユーザーID（FK → users.user_id, onDelete: cascade） |
| thread_id | NOT NULL, FOREIGN KEY | - | スレッドID（FK → threads.thread_id, onDelete: cascade） |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |
| UNIQUE制約 | (user_id, thread_id) | - | 同一ユーザーは同一スレッドを1回のみお気に入り登録可能 |

---

## 11. thread_interactions（スレッド内ユーザー間のやり取り）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| id | PRIMARY KEY | - | やり取りID（自動採番） |
| thread_id | NOT NULL, FOREIGN KEY, INDEX | - | スレッドID（FK → threads.thread_id, onDelete: cascade） |
| user_id | NOT NULL, FOREIGN KEY, INDEX | - | ユーザーID（FK → users.user_id, onDelete: cascade） |
| other_user_id | NOT NULL, FOREIGN KEY, INDEX | - | 相手ユーザーID（FK → users.user_id, onDelete: cascade） |
| message_count | NOT NULL | - | メッセージ数（unsigned integer, default: 0） |
| total_characters | NOT NULL | - | 総文字数（unsigned integer, default: 0） |
| last_interaction_at | NULL可 | - | 最後のやり取り日時 |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |
| UNIQUE制約 | (thread_id, user_id, other_user_id) | - | 同一スレッド内の同一ユーザー間のやり取りは1つのみ |

---

## 12. thread_accesses（スレッドアクセス記録）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| access_id | PRIMARY KEY | - | アクセスID（自動採番） |
| user_name | NULL可 | - | ユーザー名（ゲストの場合） |
| user_id | NULL可, FOREIGN KEY | - | ユーザーID（FK → users.user_id, onDelete: cascade） |
| thread_id | NOT NULL, FOREIGN KEY | - | スレッドID（FK → threads.thread_id, onDelete: cascade） |
| accessed_at | NOT NULL | - | アクセス日時（timestamp） |

---

## 13. thread_continuation_requests（続きスレッド要望）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| request_id | PRIMARY KEY | - | 要望ID（自動採番） |
| thread_id | NOT NULL, FOREIGN KEY | - | スレッドID（FK → threads.thread_id, onDelete: cascade） |
| user_id | NOT NULL, FOREIGN KEY | - | ユーザーID（FK → users.user_id, onDelete: cascade） |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |
| UNIQUE制約 | (thread_id, user_id) | - | 同一ユーザーは同一スレッドに1回のみ要望可能 |

---

## 14. admin_message_reads（管理者メッセージ既読）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| id | PRIMARY KEY | - | 既読ID（自動採番） |
| user_id | NULL可, FOREIGN KEY | - | ユーザーID（FK → users.user_id, onDelete: cascade） |
| admin_message_id | NOT NULL, FOREIGN KEY | - | 管理者メッセージID（FK → admin_messages.id, onDelete: cascade） |
| read_at | NOT NULL | - | 既読日時（timestamp） |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |
| UNIQUE制約 | (user_id, admin_message_id) | - | 同一ユーザーは同一メッセージを1回のみ既読記録可能 |

---

## 15. admin_message_coin_rewards（管理者メッセージコイン報酬）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| id | PRIMARY KEY | - | 報酬ID（自動採番） |
| user_id | NOT NULL, FOREIGN KEY, INDEX | - | ユーザーID（FK → users.user_id, onDelete: cascade） |
| admin_message_id | NOT NULL, FOREIGN KEY, INDEX | - | 管理者メッセージID（FK → admin_messages.id, onDelete: cascade） |
| coin_amount | NOT NULL | - | 報酬コイン額（integer） |
| received_at | NOT NULL | - | 受け取り日時（timestamp） |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |
| UNIQUE制約 | (user_id, admin_message_id) | - | 同一ユーザーは同一メッセージから1回のみコイン受け取り可能 |

---

## 16. consecutive_login_rewards（連続ログイン報酬）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| id | PRIMARY KEY | - | 報酬ID（自動採番） |
| user_id | NOT NULL, FOREIGN KEY, INDEX | - | ユーザーID（FK → users.user_id, onDelete: cascade） |
| reward_date | NOT NULL, INDEX | - | 報酬日（date） |
| coins_rewarded | NOT NULL | - | 報酬コイン数（unsigned integer） |
| consecutive_days | NOT NULL | - | 連続日数（unsigned integer） |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |

---

## 17. ad_watch_histories（広告視聴履歴）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| id | PRIMARY KEY | - | 履歴ID（自動採番） |
| user_id | NOT NULL, FOREIGN KEY, INDEX | - | ユーザーID（FK → users.user_id, onDelete: cascade） |
| watch_date | NOT NULL, INDEX | - | 視聴日（date） |
| watch_count | NOT NULL | - | 視聴回数（unsigned integer, default: 0） |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |
| UNIQUE制約 | (user_id, watch_date) | - | 同一ユーザーは同一日に1つのみ記録 |

---

## 18. user_invites（ユーザー招待）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| id | PRIMARY KEY | - | 招待ID（自動採番） |
| inviter_id | NOT NULL, FOREIGN KEY, INDEX | - | 招待者ID（FK → users.user_id, onDelete: cascade） |
| invitee_id | NOT NULL, FOREIGN KEY, UNIQUE, INDEX | - | 被招待者ID（FK → users.user_id, onDelete: cascade, 一意） |
| invite_code | NOT NULL, INDEX | 20文字 | 招待コード |
| coins_given | NOT NULL | - | 報酬コイン付与フラグ（boolean, default: false） |
| friend_request_auto_created | NOT NULL | - | 自動フレンド申請作成フラグ（boolean, default: false） |
| invited_at | NOT NULL | - | 招待日時（timestamp） |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |

---

## 19. residence_histories（居住地変更履歴）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| id | PRIMARY KEY | - | 履歴ID（自動採番） |
| user_id | NOT NULL, FOREIGN KEY | - | ユーザーID（FK → users.user_id, onDelete: cascade） |
| old_residence | NULL可 | 10文字 | 変更前の居住地 |
| new_residence | NOT NULL | 10文字 | 変更後の居住地 |
| changed_at | NOT NULL | - | 変更日時（timestamp） |
| created_at | NOT NULL | - | 作成日時 |
| updated_at | NULL可 | - | 更新日時 |

---

## 21. password_reset_tokens（パスワードリセットトークン）

## 21. user_change_logs（ユーザー変更ログ）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| log_id | PRIMARY KEY | - | ログID（自動採番） |
| user_id | NOT NULL, FOREIGN KEY, INDEX | - | 対象ユーザーID（FK → users.user_id, onDelete: cascade） |
| action_type | NOT NULL, INDEX | 50文字 | アクションタイプ（'update' | 'delete' | 'freeze' | 'unfreeze' | 'permanent_ban' | 'hide' | 'unhide'） |
| field_name | NULL可 | 100文字 | 変更されたフィールド名（update操作の場合） |
| old_value | NULL可 | - | 変更前の値（テキスト形式） |
| new_value | NULL可 | - | 変更後の値（テキスト形式） |
| changed_by_user_id | NULL可, FOREIGN KEY, INDEX | - | 変更を実行したユーザーID（FK → users.user_id, onDelete: set null） |
| ip_address | NULL可, INDEX | 45文字 | 操作を実行したIPアドレス |
| user_agent | NULL可 | - | 操作を実行したユーザーエージェント（text） |
| reason | NULL可 | - | 変更理由（text） |
| metadata | NULL可 | - | 追加情報（JSON形式） |
| changed_at | NOT NULL, INDEX | - | 変更日時（timestamp） |
| created_at | NOT NULL | - | レコード作成日時 |
| updated_at | NULL可 | - | レコード更新日時 |

---

## 22. thread_change_logs（スレッド変更ログ）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| log_id | PRIMARY KEY | - | ログID（自動採番） |
| thread_id | NOT NULL, FOREIGN KEY, INDEX | - | 対象スレッドID（FK → threads.thread_id, onDelete: cascade） |
| action_type | NOT NULL, INDEX | 50文字 | アクションタイプ（'delete' | 'hide' | 'unhide'） |
| changed_by_user_id | NULL可, FOREIGN KEY, INDEX | - | 変更を実行したユーザーID（FK → users.user_id, onDelete: set null） |
| ip_address | NULL可, INDEX | 45文字 | 操作を実行したIPアドレス |
| user_agent | NULL可 | - | 操作を実行したユーザーエージェント（text） |
| reason | NULL可 | - | 変更理由（text） |
| metadata | NULL可 | - | 追加情報（JSON形式） |
| changed_at | NOT NULL, INDEX | - | 変更日時（timestamp） |
| created_at | NOT NULL | - | レコード作成日時 |
| updated_at | NULL可 | - | レコード更新日時 |

---

## 23. response_change_logs（レスポンス変更ログ）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| log_id | PRIMARY KEY | - | ログID（自動採番） |
| response_id | NOT NULL, FOREIGN KEY, INDEX | - | 対象レスポンスID（FK → responses.response_id, onDelete: cascade） |
| action_type | NOT NULL, INDEX | 50文字 | アクションタイプ（'delete' | 'hide' | 'unhide'） |
| changed_by_user_id | NULL可, FOREIGN KEY, INDEX | - | 変更を実行したユーザーID（FK → users.user_id, onDelete: set null） |
| ip_address | NULL可, INDEX | 45文字 | 操作を実行したIPアドレス |
| user_agent | NULL可 | - | 操作を実行したユーザーエージェント（text） |
| reason | NULL可 | - | 変更理由（text） |
| metadata | NULL可 | - | 追加情報（JSON形式） |
| changed_at | NOT NULL, INDEX | - | 変更日時（timestamp） |
| created_at | NOT NULL | - | レコード作成日時 |
| updated_at | NULL可 | - | レコード更新日時 |

---

## 24. password_reset_tokens（パスワードリセットトークン）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| email | PRIMARY KEY | - | メールアドレス（主キー） |
| token | NOT NULL | - | リセットトークン |
| created_at | NULL可 | - | 作成日時（トークン発行日時） |

---

## 25. sessions（セッション）

| カラム名 | 制限 | 文字数制限 | 内容 |
|---------|------|-----------|------|
| id | PRIMARY KEY | - | セッションID（主キー） |
| user_id | NULL可, INDEX | - | ユーザーID（ログイン時のみ） |
| ip_address | NULL可 | 45文字 | IPアドレス |
| user_agent | NULL可 | - | ユーザーエージェント（text） |
| payload | NOT NULL | - | セッションデータ（longtext） |
| last_activity | NOT NULL, INDEX | - | 最終アクティビティ時刻（integer） |

---

## 外部キー制約のまとめ

### usersテーブルを参照する外部キー
- `friendships.user_id` → `users.user_id` (cascade)
- `friendships.friend_id` → `users.user_id` (cascade)
- `friend_requests.from_user_id` → `users.user_id` (cascade)
- `friend_requests.to_user_id` → `users.user_id` (cascade)
- `coin_sends.from_user_id` → `users.user_id` (cascade)
- `coin_sends.to_user_id` → `users.user_id` (cascade)
- `thread_favorites.user_id` → `users.user_id` (cascade)
- `thread_interactions.user_id` → `users.user_id` (cascade)
- `thread_interactions.other_user_id` → `users.user_id` (cascade)
- `thread_accesses.user_id` → `users.user_id` (cascade)
- `thread_continuation_requests.user_id` → `users.user_id` (cascade)
- `admin_message_reads.user_id` → `users.user_id` (cascade)
- `admin_message_coin_rewards.user_id` → `users.user_id` (cascade)
- `consecutive_login_rewards.user_id` → `users.user_id` (cascade)
- `ad_watch_histories.user_id` → `users.user_id` (cascade)
- `user_invites.inviter_id` → `users.user_id` (cascade)
- `user_invites.invitee_id` → `users.user_id` (cascade)
- `residence_histories.user_id` → `users.user_id` (cascade)
- `reports.user_id` → `users.user_id` (cascade)
- `admin_messages.user_id` → `users.user_id` (cascade)
- `user_change_logs.user_id` → `users.user_id` (cascade)
- `user_change_logs.changed_by_user_id` → `users.user_id` (set null)
- `thread_change_logs.changed_by_user_id` → `users.user_id` (set null)
- `response_change_logs.changed_by_user_id` → `users.user_id` (set null)

### threadsテーブルを参照する外部キー
- `responses.thread_id` → `threads.thread_id` (cascade)
- `thread_favorites.thread_id` → `threads.thread_id` (cascade)
- `thread_interactions.thread_id` → `threads.thread_id` (cascade)
- `thread_accesses.thread_id` → `threads.thread_id` (cascade)
- `thread_continuation_requests.thread_id` → `threads.thread_id` (cascade)
- `reports.thread_id` → `threads.thread_id` (cascade)
- `admin_messages.thread_id` → `threads.thread_id` (cascade)
- `threads.parent_thread_id` → `threads.thread_id` (set null)
- `threads.continuation_thread_id` → `threads.thread_id` (set null)
- `thread_change_logs.thread_id` → `threads.thread_id` (cascade)

### responsesテーブルを参照する外部キー
- `responses.parent_response_id` → `responses.response_id` (cascade)
- `reports.response_id` → `responses.response_id` (cascade)
- `response_change_logs.response_id` → `responses.response_id` (cascade)

### admin_messagesテーブルを参照する外部キー
- `admin_message_reads.admin_message_id` → `admin_messages.id` (cascade)
- `admin_message_coin_rewards.admin_message_id` → `admin_messages.id` (cascade)
- `admin_messages.parent_message_id` → `admin_messages.id` (cascade)

---

## インデックスのまとめ

### 単一カラムインデックス
- `users.email` (UNIQUE)
- `users.username` (UNIQUE)
- `users.user_identifier` (UNIQUE)
- `users.phone` (UNIQUE)
- `users.invite_code` (UNIQUE)
- `reports.flagged` (INDEX)
- `suggestions.user_id` (INDEX)
- `suggestions.completed` (INDEX)
- `suggestions.starred` (INDEX)
- `friendships.user_id` (INDEX)
- `friendships.friend_id` (INDEX)
- `friend_requests.from_user_id` (INDEX)
- `friend_requests.to_user_id` (INDEX)
- `friend_requests.status` (INDEX)
- `coin_sends.from_user_id` (INDEX)
- `coin_sends.to_user_id` (INDEX)
- `coin_sends.sent_at` (INDEX)
- `thread_interactions.thread_id` (INDEX)
- `thread_interactions.user_id` (INDEX)
- `thread_interactions.other_user_id` (INDEX)
- `thread_accesses.user_id` (INDEX)
- `admin_message_reads.user_id` (INDEX)
- `admin_message_coin_rewards.user_id` (INDEX)
- `admin_message_coin_rewards.admin_message_id` (INDEX)
- `consecutive_login_rewards.user_id` (INDEX)
- `consecutive_login_rewards.reward_date` (INDEX)
- `ad_watch_histories.user_id` (INDEX)
- `ad_watch_histories.watch_date` (INDEX)
- `user_invites.inviter_id` (INDEX)
- `user_invites.invite_code` (INDEX)
- `access_logs.user_id` (INDEX)
- `sessions.user_id` (INDEX)
- `sessions.last_activity` (INDEX)
- `user_change_logs.user_id` (INDEX)
- `user_change_logs.action_type` (INDEX)
- `user_change_logs.changed_by_user_id` (INDEX)
- `user_change_logs.changed_at` (INDEX)
- `user_change_logs.ip_address` (INDEX)
- `thread_change_logs.thread_id` (INDEX)
- `thread_change_logs.action_type` (INDEX)
- `thread_change_logs.changed_by_user_id` (INDEX)
- `thread_change_logs.changed_at` (INDEX)
- `thread_change_logs.ip_address` (INDEX)
- `response_change_logs.response_id` (INDEX)
- `response_change_logs.action_type` (INDEX)
- `response_change_logs.changed_by_user_id` (INDEX)
- `response_change_logs.changed_at` (INDEX)
- `response_change_logs.ip_address` (INDEX)

### 複合ユニーク制約
- `(friendships.user_id, friendships.friend_id)`
- `(friend_requests.from_user_id, friend_requests.to_user_id)`
- `(thread_favorites.user_id, thread_favorites.thread_id)`
- `(thread_interactions.thread_id, thread_interactions.user_id, thread_interactions.other_user_id)`
- `(thread_continuation_requests.thread_id, thread_continuation_requests.user_id)`
- `(admin_message_reads.user_id, admin_message_reads.admin_message_id)`
- `(admin_message_coin_rewards.user_id, admin_message_coin_rewards.admin_message_id)`
- `(ad_watch_histories.user_id, ad_watch_histories.watch_date)`
- `(reports.user_id, reports.thread_id)`
- `(reports.user_id, reports.response_id)`

