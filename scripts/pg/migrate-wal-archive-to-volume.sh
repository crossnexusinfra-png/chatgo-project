#!/usr/bin/env bash
# WAL アーカイブを専用ボリュームへ移し、/var/lib/postgresql/wal_archive をシンボリックリンクにする。
#
# 前提:
#   - 新ボリュームがマウント済み（例: /mnt/pg_archive）。空き容量は WAL 保持日数分に余裕を付けること。
#   - Debian/Ubuntu の postgresql クラスタ（systemctl / pg_ctlcluster）想定。
#
# 使い方:
#   sudo bash scripts/pg/migrate-wal-archive-to-volume.sh --check
#   sudo systemctl stop postgresql   # または: sudo pg_ctlcluster 16 main stop
#   sudo bash scripts/pg/migrate-wal-archive-to-volume.sh --execute
#   sudo systemctl start postgresql
#
# 環境変数（任意）:
#   PG_ARCHIVE_MOUNT=/mnt/pg_archive     マウントポイント
#   PG_ARCHIVE_DIRNAME=wal_archive       マウント配下のディレクトリ名
#   PG_ARCHIVE_LEGACY=/var/lib/postgresql/wal_archive  従来パス（リンク先を差し替える）

set -euo pipefail

PG_ARCHIVE_MOUNT="${PG_ARCHIVE_MOUNT:-/mnt/pg_archive}"
PG_ARCHIVE_DIRNAME="${PG_ARCHIVE_DIRNAME:-wal_archive}"
PG_ARCHIVE_LEGACY="${PG_ARCHIVE_LEGACY:-/var/lib/postgresql/wal_archive}"

NEW_PARENT="${PG_ARCHIVE_MOUNT%/}/${PG_ARCHIVE_DIRNAME}"

usage() {
  cat <<'USAGE'
Usage:
  sudo bash migrate-wal-archive-to-volume.sh --check
  sudo bash migrate-wal-archive-to-volume.sh --execute

環境変数 PG_ARCHIVE_MOUNT / PG_ARCHIVE_DIRNAME / PG_ARCHIVE_LEGACY でパスを変更可能。
--execute は PostgreSQL 停止後に実行すること。
USAGE
}

if [[ "${1:-}" != "--check" && "${1:-}" != "--execute" ]]; then
  usage
  exit 1
fi

if [[ "$(id -u)" -ne 0 ]]; then
  echo "root で実行してください（sudo）。" >&2
  exit 1
fi

if ! id postgres &>/dev/null; then
  echo "postgres ユーザーが存在しません。" >&2
  exit 1
fi

if ! mountpoint -q "${PG_ARCHIVE_MOUNT}"; then
  echo "マウントされていません: ${PG_ARCHIVE_MOUNT}" >&2
  echo "先にボリュームをマウントしてから再実行してください。" >&2
  exit 1
fi

avail_kb=$(df -Pk "${PG_ARCHIVE_MOUNT}" | awk 'NR==2 {print $4}')
if [[ -z "${avail_kb}" || "${avail_kb}" -lt 1048576 ]]; then
  echo "警告: ${PG_ARCHIVE_MOUNT} の空きが 1GB 未満です。十分な容量か確認してください。" >&2
  if [[ "${1}" == "--check" ]]; then
    exit 1
  fi
fi

if [[ -L "${PG_ARCHIVE_LEGACY}" ]]; then
  current=$(readlink -f "${PG_ARCHIVE_LEGACY}" || true)
  if [[ "${current}" == "$(readlink -f "${NEW_PARENT}" 2>/dev/null || echo "")" ]]; then
    echo "既に ${PG_ARCHIVE_LEGACY} -> ${NEW_PARENT} が設定済みです。"
    exit 0
  fi
  echo "既にシンボリックリンクです: ${PG_ARCHIVE_LEGACY} -> ${current}" >&2
  echo "手動で確認してください。" >&2
  exit 1
fi

postgres_service_running() {
  if systemctl is-active --quiet postgresql 2>/dev/null; then return 0; fi
  if systemctl is-active --quiet "postgresql@16-main" 2>/dev/null; then return 0; fi
  if command -v systemctl &>/dev/null; then
    if systemctl list-units --type=service --state=running --no-legend 2>/dev/null | grep -qE '^postgresql(@[^[:space:]]+)?\.service[[:space:]]'; then
      return 0
    fi
  fi
  return 1
}

if postgres_service_running; then
  echo "PostgreSQL が起動中です。先に停止してください。" >&2
  echo "  sudo systemctl stop postgresql" >&2
  echo "  または: sudo pg_ctlcluster 16 main stop" >&2
  exit 1
fi

if [[ "${1}" == "--check" ]]; then
  echo "OK: 事前チェック通過"
  echo "  マウント: ${PG_ARCHIVE_MOUNT}"
  echo "  新アーカイブディレクトリ: ${NEW_PARENT}"
  echo "  従来パス（移行後はシンボリックリンク）: ${PG_ARCHIVE_LEGACY}"
  echo "  空き(KB): ${avail_kb}"
  echo ""
  echo "次: PostgreSQL を停止したうえで --execute を実行してください。"
  exit 0
fi

# --execute
if [[ -d "${NEW_PARENT}" ]] && [[ -n "$(find "${NEW_PARENT}" -mindepth 1 -maxdepth 1 2>/dev/null | head -1)" ]]; then
  echo "エラー: ${NEW_PARENT} が空ではありません。中身を確認のうえ空にするか別名を指定してください。" >&2
  exit 1
fi

mkdir -p "${NEW_PARENT}"
chown postgres:postgres "${NEW_PARENT}"
chmod 700 "${NEW_PARENT}"

stamp=$(date +%Y%m%d_%H%M%S)
legacy_backup="${PG_ARCHIVE_LEGACY}.pre_volume_${stamp}"

if [[ -d "${PG_ARCHIVE_LEGACY}" ]]; then
  echo "rsync: ${PG_ARCHIVE_LEGACY}/ -> ${NEW_PARENT}/"
  rsync -a "${PG_ARCHIVE_LEGACY}/" "${NEW_PARENT}/"
  mv "${PG_ARCHIVE_LEGACY}" "${legacy_backup}"
  echo "退避: ${legacy_backup}"
elif [[ -e "${PG_ARCHIVE_LEGACY}" ]]; then
  echo "想定外: ${PG_ARCHIVE_LEGACY} がディレクトリではありません。" >&2
  exit 1
else
  echo "情報: ${PG_ARCHIVE_LEGACY} は存在しません。新規のみ作成します。"
fi

ln -s "${NEW_PARENT}" "${PG_ARCHIVE_LEGACY}"
chown -h postgres:postgres "${PG_ARCHIVE_LEGACY}" 2>/dev/null || true

echo "完了: ${PG_ARCHIVE_LEGACY} -> ${NEW_PARENT}"
echo "PostgreSQL を起動し、アーカイブを確認してください:"
echo "  sudo systemctl start postgresql"
echo "  sudo -u postgres psql -d chatgo -c \"SELECT archived_count, failed_count, last_archived_wal FROM pg_stat_archiver;\""
