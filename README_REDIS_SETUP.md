# Redis設定確認と動作テスト

## 確認手順

### 1. 基本的な動作確認

```bash
# Redisサーバーの確認
redis-cli ping
# 応答: PONG が返ってくればOK

# PHP Redis拡張の確認
php -m | grep redis
# 応答: redis が表示されればOK

# Laravel設定の確認
php artisan config:show cache.default
php artisan config:show session.driver
# 両方とも "redis" が表示されればOK
```

### 2. 詳細な動作テスト

```bash
# キャッシュとセッションの動作テスト
php test-redis-setup.php

# セッション専用のテスト
php test-redis-session.php
```

### 3. Tinkerを使った手動テスト

```bash
php artisan tinker
```

以下を実行：

```php
// キャッシュのテスト
Cache::put('test_key', 'test_value', 60);
Cache::get('test_key');  // 'test_value' が返ってくればOK

// Redisに直接接続して確認
$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);
$redis->ping();  // '+PONG' または true が返ってくればOK

// Redisに保存されているキーを確認
$redis->keys('*');  // Laravelのキャッシュキーが表示される

// セッションのテスト
session(['test' => 'value']);
session('test');  // 'value' が返ってくればOK
```

### 4. 実際のアプリケーションでの確認

1. ブラウザでアプリケーションにアクセス
2. ログインしてセッションが維持されるか確認
3. スレッド一覧など、キャッシュが使われるページにアクセス
4. Redisにデータが保存されているか確認：

```bash
redis-cli
> KEYS *
> GET laravel_cache:threads_popular
> GET laravel_session:*
```

## 確認ポイント

- [ ] Redisサーバーが起動している
- [ ] PHP Redis拡張がインストールされている
- [ ] `CACHE_DRIVER=redis` が設定されている
- [ ] `SESSION_DRIVER=redis` が設定されている
- [ ] キャッシュの保存・取得が正常に動作する
- [ ] セッションの保存・取得が正常に動作する

## トラブルシューティング

### Redisに接続できない場合

```bash
# Redisサーバーの状態確認
sudo service redis-server status

# Redisサーバーの起動
sudo service redis-server start

# ポートが使用されているか確認
netstat -tuln | grep 6379
```

### PHP Redis拡張が認識されない場合

```bash
# 拡張の再インストール
sudo apt install --reinstall php-redis

# PHP-FPMを使用している場合、再起動が必要
sudo service php8.3-fpm restart  # バージョンに応じて変更
```

### 設定が反映されない場合

```bash
# 設定キャッシュのクリア
php artisan config:clear
php artisan cache:clear

# 設定の再読み込み
php artisan config:cache
```

