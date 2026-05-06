# DB復旧Runbook（本番向け）

このドキュメントは、`chatgo` の本番DB（PostgreSQL）で障害時に復旧するための実行手順です。  
本番適用前に、必ず検証環境またはメンテナンス時間でリハーサルしてください。

## 前提

- DB: PostgreSQL 16
- 本番DB名: `chatgo`
- アプリ配置: `/var/www/crossnexus/apps/chatgo`
- WALアーカイブ先: `/var/lib/postgresql/wal_archive`
- ベースバックアップ保存先: `/var/lib/postgresql/basebackup_*`

---

## 復旧方式の選択

### 1) PITR（時点復元）

「特定時刻に戻したい」場合に使用します。  
必要な材料:

- ベースバックアップ
- WALアーカイブ

### 2) S3ダンプ復元

「取得済みダンプの時点に戻せればよい」場合に使用します。  
必要な材料:

- S3上の `db-backups/pgsql/*.dump`

---

## 事前ヘルスチェック（共通）

```bash
# WALアーカイブ状態確認
sudo -u postgres psql -c "SHOW archive_mode;"
sudo -u postgres psql -c "SHOW archive_command;"
sudo -u postgres psql -c "SELECT archived_count, failed_count, last_archived_wal, last_archived_time FROM pg_stat_archiver;"

# ベースバックアップ存在確認
sudo -u postgres bash -lc "ls -1dt /var/lib/postgresql/basebackup_* | head -n 5"
```

---

## A. PITRで本番DBを復旧する

### 注意（Debian/Ubuntu系のPostgreSQL）

Debian/Ubuntu系では `postgresql.conf` や `pg_hba.conf` が `/etc/postgresql/16/main/` 側にあり、  
データディレクトリ（`/var/lib/postgresql/16/main`）には存在しない構成があります。  
通常の `systemctl start postgresql` を使う復旧ではこの差異は吸収されますが、`pg_ctl -D ...` で直接起動して検証する場合は `-o` で設定ファイルを明示してください。

### 0. 目標時刻を決める

```bash
export TARGET_TIME="2026-04-26 19:46:00+09"
```

### 1. 復旧前の保険バックアップ

```bash
sudo -u postgres pg_dump -Fc -d chatgo -f /var/lib/postgresql/pre_recovery_$(date +%Y%m%d_%H%M%S).dump
```

### 2. アプリ停止（メンテモード）

```bash
cd /var/www/crossnexus/apps/chatgo
php artisan down
```

### 3. PostgreSQL停止

```bash
sudo systemctl stop postgresql
```

### 4. 現行データ退避

```bash
sudo mv /var/lib/postgresql/16/main /var/lib/postgresql/16/main.before_pitr_$(date +%Y%m%d_%H%M%S)
```

### 5. ベースバックアップ展開

```bash
LATEST_BASEBACKUP=$(sudo -u postgres bash -lc "ls -1dt /var/lib/postgresql/basebackup_* | head -n1")
echo "$LATEST_BASEBACKUP"

sudo cp -a "$LATEST_BASEBACKUP" /var/lib/postgresql/16/main
sudo chown -R postgres:postgres /var/lib/postgresql/16/main
sudo chmod 700 /var/lib/postgresql/16/main
```

### 6. リカバリ設定

```bash
sudo -u postgres bash -lc "cat >> /var/lib/postgresql/16/main/postgresql.auto.conf <<'EOF'
restore_command = 'cp /var/lib/postgresql/wal_archive/%f %p'
recovery_target_time = '${TARGET_TIME}'
recovery_target_action = 'promote'
EOF"

sudo -u postgres touch /var/lib/postgresql/16/main/recovery.signal
```

### 7. PostgreSQL起動

```bash
sudo systemctl start postgresql
```

### 8. 復旧確認

```bash
sudo -u postgres psql -d chatgo -c "SELECT now();"
# 必要に応じて業務テーブルを確認
```

### 9. アプリ再開

```bash
cd /var/www/crossnexus/apps/chatgo
php artisan up
```

---

## B. S3ダンプから本番DBを復旧する

### 1. アプリ停止

```bash
cd /var/www/crossnexus/apps/chatgo
php artisan down
```

### 2. ダンプ取得

```bash
php artisan db:backup:pull <S3_KEY>
```

例:

```bash
php artisan db:backup:pull db-backups/pgsql/db_pgsql_20260426_105936_s3-restore-test-20260426-1959.dump
```

### 3. 復旧前の保険バックアップ

```bash
sudo -u postgres pg_dump -Fc -d chatgo -f /var/lib/postgresql/pre_restore_$(date +%Y%m%d_%H%M%S).dump
```

### 4. ダンプ復元

```bash
sudo -u postgres pg_restore \
  --clean --if-exists --no-owner --no-privileges \
  --dbname=chatgo \
  /var/www/crossnexus/apps/chatgo/storage/app/private/backups/restore/<dumpファイル名>
```

### 5. 復旧確認

```bash
sudo -u postgres psql -d chatgo -c "SELECT count(*) FROM pg_tables WHERE schemaname='public';"
```

### 6. アプリ再開

```bash
cd /var/www/crossnexus/apps/chatgo
php artisan up
```

---

## 参考: S3バックアップ作成コマンド

```bash
cd /var/www/crossnexus/apps/chatgo
php artisan db:backup:s3 --label=manual-$(date +%Y%m%d-%H%M)
```

---

## トラブルシュート

### `pg_dump: Permission denied`（tmp書き込み失敗）

```bash
cd /var/www/crossnexus/apps/chatgo
mkdir -p storage/app/private/backups/tmp storage/app/private/backups/restore
sudo chown -R crossnexus:crossnexus storage/app/private/backups
chmod -R 775 storage/app/private/backups
```

### `database "... does not exist"`

```bash
sudo -u postgres psql -lqt
grep '^DB_DATABASE=' /var/www/crossnexus/apps/chatgo/.env
```

### 復元ログを詳細確認したい

```bash
sudo -u postgres bash -lc '
pg_restore -v --clean --if-exists --no-owner --no-privileges \
  --dbname=chatgo \
  /path/to/dump.dump > /tmp/pg_restore_verbose.log 2>&1
echo "exit_code=$?"
tail -n 80 /tmp/pg_restore_verbose.log
'
```

---

## 運用推奨

- 日次で `db:backup:s3` を実行
- 日次で `pg_basebackup` を実行（世代管理あり）
- WALアーカイブの保存期間を明確化（例: 7日/14日）
- 月1回以上、復元リハーサルを実施

### 自動クリーンアップ（再発防止）

本リポジトリには、WALアーカイブと `basebackup_*` の古い世代を削除するコマンドがあります。

```bash
cd /var/www/crossnexus/apps/chatgo
php artisan db:archive:cleanup --dry-run
php artisan db:archive:cleanup
```

Scheduler でも日次実行されます（既定: `04:10`）。  
保持日数は `.env` の下記で調整できます。

```bash
DB_ARCHIVE_CLEANUP_SCHEDULE_AT=04:10
DB_WAL_ARCHIVE_DIR=/var/lib/postgresql/wal_archive
DB_WAL_ARCHIVE_RETENTION_DAYS=14
DB_BASEBACKUP_GLOB=/var/lib/postgresql/basebackup_*
DB_BASEBACKUP_RETENTION_DAYS=14
```

