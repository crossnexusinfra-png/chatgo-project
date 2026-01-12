# テーブルカラムの処理での使用状況

## 1. thread_continuation_requestsテーブル

### **request_id** (PRIMARY KEY)
**使用状況**:
- 主キーとして使用
- レコードの一意の識別子
- リレーションでは使用されていない（`thread_id`と`user_id`の組み合わせで管理）

### **thread_id**
**使用状況**:
- **続きスレッド要望の対象スレッド**を指定
- **外部キー**: `threads.thread_id`を参照（`onDelete('cascade')`）
- **使用箇所**:
  1. **要望の作成**: `ThreadContinuationController::toggleRequest()` (63行目)
     ```php
     ThreadContinuationRequest::create([
         'thread_id' => $thread->thread_id,
         'user_id' => $user->user_id,
     ]);
     ```
  2. **要望の削除**: 既存の要望を削除する際に使用 (47行目)
  3. **要望数の取得**: `Thread::getContinuationRequestCount()` (533行目)
     - スレッド主を除いた要望数をカウント
  4. **要望の存在確認**: `Thread::hasContinuationRequestFromUser()` (546行目)
  5. **続きスレッド作成時の要望取得**: `ThreadContinuationController::createContinuationThread()` (140行目)
     - 続きスレッドが作成された際に、要望を送ったユーザーIDのリストを取得
  6. **要望のクリア**: 続きスレッド作成後、すべての要望を削除 (145行目)
     ```php
     ThreadContinuationRequest::where('thread_id', $parentThread->thread_id)->delete();
     ```

### **user_id**
**使用状況**:
- **要望を送信したユーザー**を指定
- **外部キー**: `users.user_id`を参照（`onDelete('cascade')`）
- **使用箇所**:
  1. **要望の作成**: 要望を送信するユーザーIDを保存 (65行目)
  2. **要望の削除**: 既存の要望を削除する際に使用 (42行目)
  3. **要望の存在確認**: 特定ユーザーが要望しているかチェック (546行目)
  4. **続きスレッド作成時の通知送信**: 要望を送ったユーザー全員に通知を送信 (140-142行目)
     ```php
     $requestedUserIds = ThreadContinuationRequest::where('thread_id', $parentThread->thread_id)
         ->pluck('user_id')
         ->toArray();
     ```
  5. **スレッド主の除外**: 要望数をカウントする際、スレッド主を除外 (534行目)

### **created_at**
**使用状況**:
- 要望が作成された日時（Laravelの標準機能）
- 現在はソートやフィルタリングには使用されていない

### **updated_at**
**使用状況**:
- 要望が更新された日時（Laravelの標準機能）
- 要望は作成後に更新されることがないため、実質的には`created_at`と同じ値

---

## 2. admin_message_readsテーブル

### **id** (PRIMARY KEY)
**使用状況**:
- 主キーとして使用
- レコードの一意の識別子

### **user_id**
**使用状況**:
- **メッセージを開封したユーザー**を指定
- **外部キー**: `users.user_id`を参照（`onDelete('cascade')`）
- **NULL可**: 非ログインユーザーの場合も考慮（現在は使用されていない）
- **使用箇所**:
  1. **開封記録の作成**: `NotificationsController::markAsRead()` (143-147行目)
     ```php
     AdminMessageRead::create([
         'user_id' => $userId,
         'admin_message_id' => $adminMessage->id,
         'read_at' => now(),
     ]);
     ```
  2. **開封状態の確認**: `AdminMessage::isReadBy()` (85-87行目)
     - 指定ユーザーがメッセージを読んだかどうかを判定
  3. **未読メッセージ数の計算**: `AppServiceProvider` (80-86行目)
     - ヘッダーに表示する未読お知らせ数を計算
     ```php
     $readMessageIds = AdminMessageRead::where('user_id', $userId)
         ->whereIn('admin_message_id', $messageIds)
         ->pluck('admin_message_id')
         ->toArray();
     $unreadCount = count($messageIds) - count($readMessageIds);
     ```
  4. **通知一覧での開封状態表示**: `NotificationsController::index()` (55-67行目)
     - 各メッセージの開封状態を事前に取得して表示

### **admin_message_id**
**使用状況**:
- **開封されたメッセージ**を指定
- **外部キー**: `admin_messages.id`を参照（`onDelete('cascade')`）
- **使用箇所**:
  1. **開封記録の作成**: どのメッセージを開封したかを記録 (145行目)
  2. **開封状態の確認**: メッセージごとの開封状態を確認 (86行目)
  3. **未読数の計算**: 開封済みメッセージIDを取得 (80-83行目)
  4. **ユニーク制約**: `['user_id', 'admin_message_id']`でユニーク制約
     - 1ユーザーは1メッセージを1回だけ開封記録できる

### **read_at**
**使用状況**:
- **メッセージが開封された日時**を記録
- **型**: `timestamp`（`datetime`にキャスト）
- **使用箇所**:
  1. **開封記録の作成**: 開封日時を記録 (146行目)
  2. **現在はソートやフィルタリングには使用されていない**
  3. **将来の拡張**: 開封履歴の分析などに活用可能

### **created_at**
**使用状況**:
- 開封記録が作成された日時（Laravelの標準機能）
- 実質的には`read_at`と同じ値

### **updated_at**
**使用状況**:
- 開封記録が更新された日時（Laravelの標準機能）
- 開封記録は作成後に更新されることがないため、実質的には`created_at`と同じ値

---

## 3. admin_message_coin_rewardsテーブル

### **id** (PRIMARY KEY)
**使用状況**:
- 主キーとして使用
- レコードの一意の識別子

### **user_id**
**使用状況**:
- **コインを受け取ったユーザー**を指定
- **外部キー**: `users.user_id`を参照（`onDelete('cascade')`）
- **使用箇所**:
  1. **コイン受け取り記録の作成**: `NotificationsController::receiveCoin()` (269-274行目)
     ```php
     AdminMessageCoinReward::create([
         'user_id' => $userId,
         'admin_message_id' => $message->id,
         'coin_amount' => $message->coin_amount,
         'received_at' => now(),
     ]);
     ```
  2. **コイン受け取り状態の確認**: `AdminMessage::hasReceivedCoin()` (71-73行目)
     - 指定ユーザーがメッセージからコインを受け取ったかどうかを判定
  3. **通知一覧での受け取り状態表示**: `NotificationsController::index()` (60-67行目)
     - 各メッセージのコイン受け取り状態を事前に取得して表示

### **admin_message_id**
**使用状況**:
- **コインが付与されたメッセージ**を指定
- **外部キー**: `admin_messages.id`を参照（`onDelete('cascade')`）
- **使用箇所**:
  1. **コイン受け取り記録の作成**: どのメッセージからコインを受け取ったかを記録 (271行目)
  2. **コイン受け取り状態の確認**: メッセージごとの受け取り状態を確認 (72行目)
  3. **ユニーク制約**: `['user_id', 'admin_message_id']`でユニーク制約
     - 1ユーザーは1メッセージから1回だけコインを受け取れる

### **coin_amount**
**使用状況**:
- **受け取ったコインの数量**を記録
- **型**: `integer`
- **使用箇所**:
  1. **コイン受け取り記録の作成**: メッセージに設定されたコイン数量を記録 (272行目)
     ```php
     'coin_amount' => $message->coin_amount,
     ```
  2. **履歴の保持**: 受け取ったコイン数量の履歴を保持
  3. **注意**: メッセージの`coin_amount`が変更されても、既に受け取った記録の`coin_amount`は変更されない

### **received_at**
**使用状況**:
- **コインを受け取った日時**を記録
- **型**: `timestamp`（`datetime`にキャスト）
- **使用箇所**:
  1. **コイン受け取り記録の作成**: 受け取り日時を記録 (273行目)
  2. **現在はソートやフィルタリングには使用されていない**
  3. **将来の拡張**: 受け取り履歴の分析などに活用可能

### **created_at**
**使用状況**:
- コイン受け取り記録が作成された日時（Laravelの標準機能）
- 実質的には`received_at`と同じ値

### **updated_at**
**使用状況**:
- コイン受け取り記録が更新された日時（Laravelの標準機能）
- コイン受け取り記録は作成後に更新されることがないため、実質的には`created_at`と同じ値

---

## 各テーブルの処理フロー

### thread_continuation_requestsテーブル

1. **要望の作成**
   - ユーザーが続きスレッドを要望
   - `thread_id`と`user_id`を保存
   - 既に要望している場合は削除（トグル機能）

2. **要望数の確認**
   - スレッド主以外の要望数が3以上かチェック
   - スレッド主の要望があるかチェック

3. **続きスレッドの作成**
   - 条件を満たした場合、続きスレッドを自動作成
   - 要望を送ったユーザーIDのリストを取得
   - すべての要望を削除（クリア）

4. **通知の送信**
   - 要望を送ったユーザー全員に通知を送信

### admin_message_readsテーブル

1. **メッセージの表示**
   - 通知一覧でメッセージを表示
   - 各メッセージの開封状態を事前に取得

2. **開封記録の作成**
   - ユーザーがメッセージを開封（`markAsRead`エンドポイント）
   - `user_id`、`admin_message_id`、`read_at`を保存
   - ユニーク制約により、重複記録を防止

3. **未読数の計算**
   - ヘッダーに表示する未読お知らせ数を計算
   - 開封済みメッセージIDを取得して、未読数を算出

### admin_message_coin_rewardsテーブル

1. **メッセージの表示**
   - 通知一覧でメッセージを表示
   - 各メッセージのコイン受け取り状態を事前に取得

2. **コイン受け取り**
   - ユーザーがコイン受け取りボタンをクリック
   - `user_id`、`admin_message_id`、`coin_amount`、`received_at`を保存
   - ユーザーのコイン残高にコインを追加
   - ユニーク制約により、重複受け取りを防止

3. **受け取り状態の確認**
   - メッセージごとに、ユーザーが既にコインを受け取ったかチェック
   - 受け取り済みの場合は、ボタンを無効化

---

## まとめ

| テーブル | 主な用途 | 重要なカラム |
|---------|---------|------------|
| `thread_continuation_requests` | 続きスレッド要望の管理 | `thread_id`, `user_id` |
| `admin_message_reads` | メッセージ開封記録の管理 | `user_id`, `admin_message_id`, `read_at` |
| `admin_message_coin_rewards` | コイン受け取り記録の管理 | `user_id`, `admin_message_id`, `coin_amount`, `received_at` |

### 共通の特徴

1. **ユニーク制約**: すべてのテーブルで、ユーザーと対象（スレッド/メッセージ）の組み合わせでユニーク制約が設定されている
2. **履歴の保持**: すべてのテーブルで、操作の履歴を保持している
3. **外部キー制約**: すべてのテーブルで、関連するテーブルへの外部キー制約が設定されている
4. **タイムスタンプ**: `created_at`と`updated_at`はLaravelの標準機能で自動管理されている

